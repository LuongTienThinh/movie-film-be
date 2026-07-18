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
        // $films->where('server', '!=', 'kkphim');
        
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
        
        if ($tableName != '') {
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
        $films = $films->joinSub(
            DB::table('films')
                ->selectRaw('slug, MAX(updated_at) AS updated_at, MIN(id) as id')
                ->groupBy('slug'),
            'latest_films',
            function ($join) {
                $join->on('films.slug', '=', 'latest_films.slug')
                    ->whereColumn('films.updated_at', '=', 'latest_films.updated_at');
            }
        );

        return $films;
    }


    public function formatFilm(Film $film, array $fields = [])
    {
        $addFormat = [];

        $formatFields = [
            'is_view'           => 'boolean',
            'is_follow'         => 'boolean',
            'is_delete'         => 'boolean',
            "episode_current"   => 'int',
            "episode_total"     => 'int',
            "year"              => 'int',
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
            // "status"        => $film->status->name,
            // "type"          => $film->type->name,
            // "genres"        => $film->genres->makeHidden('pivot'),
            // "countries"     => $film->countries->makeHidden('pivot'),
            // "episodes"      => $film->episodes,
            "description"   => strip_tags($film->description),
            ...$addFormat,
            ...$fields,
        ];
    }

    public function formatListFilms(Collection $listFilm)
    {
        return $listFilm->map(function ($film, $index) {
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
}