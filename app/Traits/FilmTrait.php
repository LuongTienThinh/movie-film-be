<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Film;
use DB;

trait FilmTrait
{
    public function getApiFilm(Request $request, Builder $films, string $tableName = 'films')
    {
        $pagination = $this->getPageManage($request, $films->count());

        $listFilms = $this->distinctSlug($films)
            ->where('is_delete', 0)
            ->orderByDesc("$tableName.updated_at")
            ->skip(($pagination["currentPage"] - 1) * $pagination["perPage"])
            ->take($pagination["perPage"])
            ->get();

        return $this->getFilmsAndPagination($listFilms, $pagination);
    }

    public function distinctSlug(Builder $films) {
        return $films->join(
            DB::raw('(SELECT slug, MAX(updated_at) AS updated_at FROM films GROUP BY slug) AS latest_films'),
            'films.slug', '=', 'latest_films.slug'
        )
        ->whereColumn('films.updated_at', '=', 'latest_films.updated_at');
    }

    public function formatFilm(Film $film)
    {
        $addFormat = [];

        $fields = [
            'is_view'           => 'boolean',
            'is_follow'         => 'boolean',
            'is_delete'         => 'boolean',
            "episode_current"   => 'int',
            "episode_total"     => 'int',
            "year"              => 'int',
        ];
        
        foreach ($fields as $field => $type) {
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
            "status"        => $film->status->name,
            "type"          => $film->type->name,
            "genres"        => $film->genre,
            "countries"     => $film->country,
            "episodes"      => $film->episode,
            "description"   => strip_tags($film->description),
            ...$addFormat
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
        $page = intval($request->page);
        $perPage = intval($request->perPage) | 8;
        $totalPage = ceil($totalItem / $perPage);

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