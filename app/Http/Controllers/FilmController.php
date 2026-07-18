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

            $film = Film::create([
                ...$validated,
                'is_delete'  => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            $film->type()->attach($validated['type_id']);
            $film->status()->attach($validated['status_id']);
            $film->genres()->attach($validated['genres']);
            $film->countries()->attach($validated['countries']);

            return $this->successResponse($film, 201, "Film created successfully!");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function updateFilm(FilmRequest $request, $filmId) {
        try {
            $film = Film::findOrFail($filmId);

            $film->update($request->validated());

            $film->type()->sync($film['type_id']);
            $film->status()->sync($film['status_id']);
            $film->genres()->sync($film['genres']);
            $film->countries()->sync($film['countries']);

            return $this->successResponse($film, 201, "Film udpated successfully!");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function deleteFilm($filmId) {
        try {
            $film = Film::findOrFail($filmId);

            $film->is_delete = 1;
            $film->save();

            return $this->successResponse($film, 201, "Film deleted successfully!");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getFilmDetail(Request $request)
    {
        try {
            $film = Film::query()
                ->where("id", '=', $request->id)
                ->where("slug", '=', $request->slug)
                ->where('is_delete', 0)
                ->first();

            $data = $this->formatFilm($film, [
                "status"        => $film->status->name,
                "type"          => $film->type->name,
                "genres"        => $film->genres->makeHidden('pivot'),
                "countries"     => $film->countries->makeHidden('pivot'),
                "episodes"      => $film->episodes
            ]);

            return $this->successResponse($data, 200, "Get film detail success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getFilteredFilms(Request $request)
    {
        $request->validate([
            'page'    => 'nullable|integer|min:1',
            'perPage' => 'nullable|integer|min:1|max:50',
            'q'       => 'nullable|string',
            'type'    => 'nullable|string',
            'genre'   => 'nullable|string|exists:genres,slug',
            'year'    => 'nullable|integer|min:1900|max:2100',
        ]);

        try {
            $films = Film::query()->select(
                'films.id',
                'films.name',
                'films.year',
                'films.slug',
                'films.description',
                'films.thumbnail_url',
                'films.poster_url',
                'films.episode_current',
                'films.quality',
            )->with('genres');

            $data = $this->getApiFilm($request, $films);

            return $this->successResponse($data, 200, "Get filtered films success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getLatestFilm(Request $request)
    {
        try {
            $films = Film::query()->select(
                'films.id',
                'films.name',
                'films.year',
                'films.slug',
                'films.description',
                'films.thumbnail_url',
                'films.poster_url',
                'films.episode_current',
                'films.quality',
                'films.year',
            )->with("genres");

            $data = $this->getApiFilm($request, $films);

            return $this->successResponse($data, 200, "Get latest films success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage(). ' at ' . $e->getFile() . ' line ' . $e->getLine());
        }
    }

    public function getSeriesFilms(Request $request)
    {
        try {
            $films = Film::query()->select(
                'films.id',
                'films.name',
                'films.year',
                'films.slug',
                'films.thumbnail_url',
                'films.poster_url',
            )->where("type_id", "=", 2);

            $data = $this->getApiFilm($request, $films);

            return $this->successResponse($data, 200, "Get series films success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getMovieFilms(Request $request)
    {
        try {
            $films = Film::query()->select(
                'films.id',
                'films.name',
                'films.year',
                'films.slug',
                'films.thumbnail_url',
                'films.poster_url',
            )->where("type_id", "=", 1);

            $data = $this->getApiFilm($request, $films);

            return $this->successResponse($data, 200, "Get movie films success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getFilmBySearch(Request $request)
    {
        try {
            $searchFilms = $this->distinctSlug(Film::fullTextSearch(["name", "origin_name"], $request->search))->where('is_delete', 0);
            \Log::info($searchFilms->toSql());

            return $this->successResponse($searchFilms->get(), 200, "Get search films success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getFilmByGenre(Request $request)
    {
        try {
            $films = Film::query()->with("genres")->whereHas(
                "genres",
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
            $films = Film::query()->with("countries")->whereHas(
                "countries",
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

    public function getWishlistByUserID(Request $request, $userId)
    {
        try {
            $films = User::find($userId)
                ->films()
                ->getQuery()
                ->select('films.id as id', 'films.*', 'is_follow', 'views')
                ->where(function ($query) {
                    $query->where('is_follow', '=', true)
                            ->orWhere('views', '>', 0);
                });
                                        
            $data = $this->getApiFilm($request, $films, 'user_film');

            return $this->successResponse($data, 200, "Get user films success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getWishlistFollowByUserID(Request $request, $userId)
    {
        try {
            $films = User::find($userId)
                ->films()
                ->getQuery()
                ->select('films.id as id', 'films.*', 'is_follow', 'views')
                ->where('is_follow', '=', true);

            $data = $this->getApiFilm($request, $films, 'user_film');

            return $this->successResponse($data, 200, "Get user followed films success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getWishlistViewedByUserID(Request $request, $userId)
    {
        try {
            $films = User::find($userId)
                ->films()
                ->getQuery()
                ->select('films.id as id', 'films.*', 'is_follow', 'views')
                ->where('views', '>', 0);

            $data = $this->getApiFilm($request, $films, 'user_film', 'views');

            return $this->successResponse($data, 200, "Get user viewed films success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getWishlistDetailByUserID(Request $request, $userId, $filmId)
    {
        try {
            $userFilm = UserFilm::query()->where('user_id', $userId)->where('film_id', $filmId)->first();
            
            if (!$userFilm) {
                $userFilm = UserFilm::create([
                    'user_id'   => $userId,
                    'film_id'   => $filmId,
                    'is_follow' => false,
                    'views'     => 0,
                ]);
            }

            $film = User::find($userId)
                ->films()
                ->select('films.id as id', 'films.*', 'is_follow', 'views')
                ->where('user_film.film_id', '=', $filmId)
                ->where('films.is_delete', 0)
                ->first();

            $data = $this->formatFilm($film, [
                "status"        => $film->status->name,
                "type"          => $film->type->name,
                "genres"        => $film->genres->makeHidden('pivot'),
                "countries"     => $film->countries->makeHidden('pivot'),
                "episodes"      => $film->episodes
            ]);

            return $this->successResponse($data, 200, "Get film detail success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getTopViewedFilms(Request $request) {
        try {
            $films = Film::query()->select(
                'films.id',
                'films.name',
                'films.description',
                'films.year',
                'films.slug',
                'films.thumbnail_url',
                'films.poster_url',
            );

            $data = $this->getApiFilm($request, $films, '', 'views');

            return $this->successResponse($data, 200, "Get movie films success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getUpdatedFilmsByUser(Request $request, int $userId) {
        try {
            $films = User::find($userId)
                ->films()
                ->getQuery()
                ->select(
                    'films.id',
                    'films.name',
                    'films.slug',
                    'films.poster_url',
                )
                ->whereBetween('films.updated_at', [Carbon::today(), Carbon::now()])
                ->orWhere(function ($query) {
                    $query->where('is_follow', '=', true);
                });

            $data = $this->getApiFilm($request, $films, 'user_film', 'is_follow');

            return $this->successResponse($data, 200, "Get user films success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function saveUserFilm(Request $request, int $userId, int $filmId)
    {
        try {
            $viewed = filter_var($request->viewed, FILTER_VALIDATE_BOOLEAN);
            $followed = filter_var($request->followed, FILTER_VALIDATE_BOOLEAN);

            $film = Film::find($filmId);

            if (!$film) {
                return $this->errorResponse(404, "Film not found.");
            }

            $film->users()->syncWithoutDetaching($userId);

            $userFilm = UserFilm::firstOrNew([
                'user_id' => $userId,
                'film_id' => $filmId,
            ]);

            if (isset($viewed) && $viewed) {
                $userFilm->views += 1;
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
