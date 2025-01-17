<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\Film;
use App\Models\User;
use App\Models\UserFilm;
use App\Traits\FilmTrait;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use App\Http\Requests\FilmRequest;
use Carbon\Carbon;
use DB;

class FilmController extends Controller
{
    use FilmTrait;
    use ApiResponseTrait;

    public function createFilm(FilmRequest $request) {
        try {
            $validated = $request->validated();

            Film::create([
                ...$validated,
                'is_delete'  => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            return $this->successResponse($data, 201, "Film created successfully!");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function updateFilm(FilmRequest $request, $filmId) {
        try {
            $film = Film::findOrFail($filmId);

            $film->update($request->validated());

            return $this->successResponse($data, 201, "Film udpated successfully!");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function deleteFilm($filmId) {
        try {
            $film = Film::findOrFail($filmId);

            $film->is_delete = 1;
            $film->save();

            return $this->successResponse($data, 201, "Film deleted successfully!");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getFilmDetail(Request $request)
    {
        try {
            $film = Film::query()->where("slug", '=', $request->slug)->where('is_delete', 0)->first();

            $data = $this->formatFilm($film);

            return $this->successResponse($data, 200, "Get film detail success.");
        } catch (Exception $e) {
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
            $searchFilms = $this->distinctSlug(Film::fullTextSearch(["name", "origin_name"], $request->search))->where('is_delete', 0)->get();

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

    public function getWishlistByUserID(Request $request, int $userId)
    {
        try {
            $films = User::find($userId)->films()
                                        ->getQuery()
                                        ->select('films.id as id', 'films.*', 'is_follow', 'is_view')
                                        ->where(function ($query) {
                                            $query->where('is_follow', '=', true)
                                                  ->orWhere('is_view', '=', true);
                                        });
                                        
            $data = $this->getApiFilm($request, $films, 'user_film');

            return $this->successResponse($data, 200, "Get user films success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getWishlistDetailByUserID(Request $request, int $userId, int $filmId)
    {
        try {
            $userFilm = UserFilm::query()->where('user_id', $userId)->where('film_id', $filmId)->first();
            
            if (!$userFilm) {
                $film = UserFilm::create([
                    'user_id'   => $userId,
                    'film_id'   => $filmId,
                    'is_follow' => false,
                    'is_view'   => false,
                ]);
            }

            $film = User::find($userId)->films()
                                        ->select('films.id as id', 'films.*', 'is_follow', 'is_view')
                                        ->where('user_film.film_id', '=', $filmId)
                                        ->where('films.is_delete', 0)
                                        ->first();

                                        
            $data = $this->formatFilm($film);

            return $this->successResponse($data, 200, "Get film detail success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function saveUserFilm(Request $request, int $userId, int $filmId)
    {
        try {
            $viewed = filter_var($request->viewed, FILTER_VALIDATE_BOOLEAN);
            $followed = filter_var($request->followed, FILTER_VALIDATE_BOOLEAN);

            $userFilm = UserFilm::query()->where('user_id', $userId)->where('film_id', $filmId)->first();

            if (!$userFilm) {
                $userFilm = UserFilm::create([
                    'user_id' => $userId,
                    'film_id' => $filmId,
                ]);
            }

            if (isset($viewed)) {
                $userFilm->is_view = $viewed;
            }

            if (isset($followed)) {
                $userFilm->is_follow = $followed;
            }
            $userFilm->save();

            return $this->successResponse($userFilm, 200, "Save user film success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }
}
