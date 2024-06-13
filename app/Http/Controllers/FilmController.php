<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\Film;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class FilmController extends Controller
{
    use ApiResponseTrait;

    public function getFilmDetail(Request $request)
    {
        try {
            $film = Film::query()->where("slug", '=', $request->slug)->first();

            $data = $this->formatFilm($film);

            return $this->successResponse($data, 200, "Get film detail success.");
        } catch (Exception $e) {
            dd('exception was threw');
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getLatestFilm(Request $request)
    {
        try {
            $films = Film::query();

            $data = $this->getApiFilm($request, $films);

            return $this->successResponse($data, 200, "Get latest films success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getSeriesFilms(Request $request)
    {
        try {
            $films = Film::query()->where("type_id", "=", 2);

            $data = $this->getApiFilm($request, $films);

            return $this->successResponse($data, 200, "Get series films success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getMovieFilms(Request $request)
    {
        try {
            $films = Film::query()->where("type_id", "=", 1);

            $data = $this->getApiFilm($request, $films);

            return $this->successResponse($data, 200, "Get movie films success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getFilmBySearch(Request $request)
    {
        try {
            $searchFilms = Film::fullTextSearch(["name", "origin_name"], $request->search)->get();

            return $this->successResponse($searchFilms, 200, "Get search films success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getFilmByGenre(Request $request)
    {
        try {
            $films = Film::query()->with("genre")->whereHas(
                "genre",
                function ($query) use ($request) {
                    $query->where("genres.slug", "=", $request->slug);
                }
            );

            $data = $this->getApiFilm($request, $films);

            return $this->successResponse($data, 200, "Get films by genre success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getFilmByCountry(Request $request)
    {
        try {
            $films = Film::query()->with("country")->whereHas(
                "country",
                function ($query) use ($request) {
                    $query->where("countries.slug", "=", $request->slug);
                }
            );

            $data = $this->getApiFilm($request, $films);

            return $this->successResponse($data, 200, "Get films by country success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    private function getApiFilm(Request $request, Builder $films)
    {
        $pagination = $this->getPageManage($request, $films->count());

        $listFilms = $films->orderByDesc("updated_at")
            ->skip(($pagination["currentPage"] - 1) * $pagination["perPage"])
            ->take($pagination["perPage"])
            ->get();

        return $this->getFilmsAndPagination($listFilms, $pagination);
    }

    private function formatFilm(Film $film)
    {
        return [
            ...$film->toArray(),
            "status" => $film->status->name,
            "type" => $film->type->name,
            "genres" => $film->genre,
            "countries" => $film->country,
            "episodes" => $film->episode,
        ];
    }

    private function formatListFilms(Collection $listFilm)
    {
        return $listFilm->map(function ($film, $index) {
            return $this->formatFilm($film);
        });
    }

    private function getPageManage(Request $request, int $totalItem)
    {
        error_log("1. {$request->page}");
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

    private function getFilmsAndPagination(Collection $listFilm, array $pagination)
    {
        $data = $this->formatListFilms($listFilm);

        return [
            "movie" => $data,
            "pagination" => $pagination
        ];
    }
}
