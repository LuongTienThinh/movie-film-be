<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Film;
use App\Models\Type;
use Illuminate\Support\Facades\DB;

trait FilmTrait
{
    public function applyFilmFilters(Builder $films, Request $request): Builder
    {
        if ($request->filled('q')) {
            $q = trim($request->q);
            if ($q !== '') {
                $films->fullTextSearch(["name", "origin_name"], $q);
            }
        }

        if ($request->filled('type')) {
            $type = $request->type;

            if (is_numeric($type)) {
                $films->where('films.type_id', (int) $type);
            } else {
                $typeId = Type::where('slug', $type)->value('id');

                if ($typeId) {
                    $films->where('films.type_id', $typeId);
                } else {
                    $films->whereRaw('1 = 0');
                }
            }
        }

        if ($request->filled('year')) {
            $films->where('films.year', (int) $request->year);
        }

        if ($request->filled('genre')) {
            $films->whereHas('genres', function ($query) use ($request) {
                $query->where('genres.slug', $request->genre);
            });
        }

        if ($request->filled('country')) {
            $films->whereHas('countries', function ($query) use ($request) {
                $query->where('countries.slug', $request->country);
            });
        }

        return $films;
    }

    public function getApiFilm(Request $request, Builder $films, string $tableName = 'films', string $order = 'updated_at')
    {
        $films = $this->applyFilmFilters($films, $request);

        $listFilms = $this->distinctSlug($films)
            ->where('is_delete', 0)
            ->withSum(['userFilm as views' => function ($query) {
                $query->where('views', '>', 0);
            }], 'views');
        
        if ($request->filled('sort')) {
            $sort = $request->string('sort')->toString();
            $direction = $request->string('order')->lower()->toString() === 'asc' ? 'asc' : 'desc';
            $column = $sort === 'views' ? 'views' : 'films.' . $sort;
            $listFilms = $listFilms->orderBy($column, $direction)->orderByDesc('films.id');
        } elseif ($tableName != '') {
            $listFilms = $listFilms->orderByDesc("$tableName.$order")->orderByDesc("$tableName.updated_at");
        } else {
            $listFilms = $listFilms->orderByDesc($order);
        }

        $pagination = $this->getPageManage($request, $listFilms->count());

        $listFilms = $listFilms->skip(($pagination["currentPage"] - 1) * $pagination["perPage"])
            ->take($pagination["perPage"])
            ->get();

        return $this->getFilmsAndPagination($listFilms, $pagination);
    }

    public function distinctSlug(Builder $films) {
        // $films = $films->joinSub(
        //     DB::table('films')
        //         ->selectRaw('slug, MAX(updated_at) AS updated_at, MIN(id) as id')
        //         ->groupBy('slug'),
        //     'latest_films',
        //     function ($join) {
        //         $join->on('films.slug', '=', 'latest_films.slug')
        //             ->whereColumn('films.updated_at', '=', 'latest_films.updated_at');
        //     }
        // );

        return $films;
    }


    public function batchResolveCloudAssets($listFilm)
    {
        $collection = $listFilm instanceof Collection ? $listFilm : collect($listFilm);

        if ($collection->isEmpty()) {
            return $listFilm;
        }

        $filmIds = $collection->pluck('id')->filter()->unique()->toArray();
        $episodeIds = [];

        foreach ($collection as $film) {
            if (is_object($film) && method_exists($film, 'relationLoaded') && $film->relationLoaded('episodes') && $film->episodes) {
                foreach ($film->episodes as $episode) {
                    if (isset($episode->id)) {
                        $episodeIds[] = $episode->id;
                    }
                }
            }
        }

        $cloudAssets = DB::table('cloud_assets')
            ->where('status', 'success')
            ->whereNotNull('storage_url')
            ->where('storage_url', '!=', '')
            ->where(function ($query) use ($filmIds, $episodeIds) {
                if (! empty($filmIds)) {
                    $query->orWhere(function ($q) use ($filmIds) {
                        $q->whereIn('resource_type', ['film_thumbnail', 'film_poster', 'film_trailer'])
                          ->whereIn('resource_type_id', $filmIds);
                    });
                }
                if (! empty($episodeIds)) {
                    $query->orWhere(function ($q) use ($episodeIds) {
                        $q->where('resource_type', 'episode')
                          ->whereIn('resource_type_id', $episodeIds);
                    });
                }
            })
            ->get(['resource_type', 'resource_type_id', 'storage_url']);

        $assetMap = [];
        foreach ($cloudAssets as $asset) {
            $key = "{$asset->resource_type}_{$asset->resource_type_id}";
            $assetMap[$key] = $this->formatCloudAssetUrl($asset->resource_type, $asset->storage_url);
        }

        foreach ($collection as $film) {
            if (! is_object($film)) {
                continue;
            }

            $posterKey = "film_poster_{$film->id}";
            if (isset($assetMap[$posterKey]) && $assetMap[$posterKey] !== '') {
                $film->poster_url = $assetMap[$posterKey];
            }

            $thumbKey = "film_thumbnail_{$film->id}";
            if (isset($assetMap[$thumbKey]) && $assetMap[$thumbKey] !== '') {
                $film->thumbnail_url = $assetMap[$thumbKey];
            }

            $trailerKey = "film_trailer_{$film->id}";
            if (isset($assetMap[$trailerKey]) && $assetMap[$trailerKey] !== '') {
                $film->trailer_url = $assetMap[$trailerKey];
            }

            if (method_exists($film, 'relationLoaded') && $film->relationLoaded('episodes') && $film->episodes) {
                foreach ($film->episodes as $episode) {
                    $epKey = "episode_{$episode->id}";
                    if (isset($assetMap[$epKey]) && $assetMap[$epKey] !== '') {
                        $episode->link = $assetMap[$epKey];
                    }
                }
            }
        }

        return $listFilm;
    }

    public function resolveSingleFilmCloudAssets(Film $film, array &$fields = []): Film
    {
        $this->batchResolveCloudAssets(collect([$film]));

        if (isset($fields['episodes'])) {
            $episodes = $fields['episodes'];
            $epIds = [];

            if (is_iterable($episodes)) {
                foreach ($episodes as $ep) {
                    $id = is_object($ep) ? ($ep->id ?? null) : ($ep['id'] ?? null);
                    if ($id) {
                        $epIds[] = $id;
                    }
                }
            }

            if (! empty($epIds)) {
                $epAssets = DB::table('cloud_assets')
                    ->where('resource_type', 'episode')
                    ->whereIn('resource_type_id', $epIds)
                    ->where('status', 'success')
                    ->whereNotNull('storage_url')
                    ->where('storage_url', '!=', '')
                    ->pluck('storage_url', 'resource_type_id');

                if ($epAssets->isNotEmpty()) {
                    if (is_iterable($episodes)) {
                        foreach ($episodes as $idx => $ep) {
                            $id = is_object($ep) ? ($ep->id ?? null) : ($ep['id'] ?? null);
                            if ($id && isset($epAssets[$id])) {
                                if (is_object($ep)) {
                                    $ep->link = $epAssets[$id];
                                } else {
                                    $fields['episodes'][$idx]['link'] = $epAssets[$id];
                                }
                            }
                        }
                    }
                }
            }
        }

        return $film;
    }

    public function formatFilm(Film $film, array $fields = [])
    {
        $this->resolveSingleFilmCloudAssets($film, $fields);

        $addFormat = [];

        $formatFields = [
            'is_view'           => 'boolean',
            'is_follow'         => 'boolean',
            'is_delete'         => 'boolean',
            'episode_current'   => 'int',
            'episode_total'     => 'int',
            'year'              => 'int',
            'server'            => 'string',
        ];
        
        foreach ($formatFields as $field => $type) {
            if (isset($film->$field)) {
                switch ($type) {
                    case 'boolean':
                        $addFormat[$field] = (int) $film->$field;
                        break;
                    case 'int':
                        $addFormat[$field] = (int) $film->$field;
                        break;
                    case 'string':
                        $addFormat[$field] = (string) $film->$field;
                        break;
                }
            }
        }

        return [
            ...$film->toArray(),
            "description"   => strip_tags($film->description),
            ...$addFormat,
            ...$fields,
        ];
    }

    public function formatListFilms($listFilm)
    {
        $listFilm = $this->batchResolveCloudAssets($listFilm);

        $collection = $listFilm instanceof Collection ? $listFilm : collect($listFilm);

        return $collection->map(function ($film, $index) {
            return $this->formatFilm($film);
        });
    }

    public function getPageManage(Request $request, int $totalItem)
    {
        $page = max(1, intval($request->page) ?: 1);
        $perPage = intval($request->perPage);
        $perPage = ($perPage && $perPage > 0) ? min($perPage, 50) : 8;
        $totalPage = ceil($totalItem / $perPage);

        if ($totalPage > 0 && $page > $totalPage) {
            $page = $totalPage;
        }

        return [
            "currentPage" => $page,
            "perPage" => $perPage,
            "totalItem" => $totalItem,
            "totalPage" => $totalPage
        ];
    }

    public function getFilmsAndPagination(Collection $listFilm, array $pagination)
    {
        $data = $this->formatListFilms($listFilm);

        return [
            "movie" => $data,
            "pagination" => $pagination
        ];
    }

    private function formatCloudAssetUrl(string $resourceType, string $url): string
    {
        if (in_array($resourceType, ['film_poster', 'film_thumbnail'], true)) {
            if (preg_match('/(?:id=|\/d\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
                return "https://lh3.googleusercontent.com/d/{$matches[1]}";
            }
        }

        return $url;
    }
}
