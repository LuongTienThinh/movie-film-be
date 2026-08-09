<?php

namespace App\Http\Controllers;

use App\Models\Film;
use App\Models\UserFilm;
use App\Traits\FilmTrait;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\Request;
use App\Http\Requests\FilmRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
        $validated = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'slug' => ['required', 'string', 'max:255'],
        ]);

        try {
            $film = Film::query()
                ->with(['status', 'type', 'genres', 'countries', 'episodes'])
                ->where("id", $validated['id'])
                ->where("slug", $validated['slug'])
                ->where('is_delete', 0)
                ->first();

            if (! $film) {
                return $this->errorResponse(404, 'Film not found.');
            }

            $data = $this->formatFilm($film, [
                "status"        => $film->status->name,
                "type"          => $film->type->name,
                "genres"        => $film->genres->makeHidden('pivot'),
                "countries"     => $film->countries->makeHidden('pivot'),
                "episodes"      => $film->episodes
            ]);

            return $this->successResponse($data, 200, "Get film detail success.");
        } catch (\Throwable $e) {
            Log::error('Unable to load film detail', ['exception' => $e]);
            return $this->errorResponse(500, 'Unable to load film detail.');
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
            'country' => 'nullable|string|exists:countries,slug',
            'year'    => 'nullable|integer|min:1900|max:2100',
            'sort'    => 'nullable|in:updated_at,year,name,views',
            'order'   => 'nullable|in:asc,desc',
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
                'films.server',
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
                'films.server',
            )->with("genres");

            $data = $this->getApiFilm($request, $films);

            return $this->successResponse($data, 200, "Get latest films success.");
        } catch (\Throwable $e) {
            Log::error('Unable to load latest films', ['exception' => $e]);
            return $this->errorResponse(500, 'Unable to load latest films.');
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
                'films.server',
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
                'films.server',
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
            Log::info($searchFilms->toSql());

            $data = $this->formatListFilms($searchFilms->get());

            return $this->successResponse($data, 200, "Get search films success.");
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

    public function getWishlistByUserID(Request $request)
    {
        try {
            $films = $request->user()
                ->films()
                ->getQuery()
                ->select('films.id as id', 'films.*', 'user_film.is_follow', 'user_film.views')
                ->where(function ($query) {
                    $query->where('user_film.is_follow', true)
                        ->orWhere('user_film.views', '>', 0);
                });
                                        
            $data = $this->getApiFilm($request, $films, 'user_film');

            return $this->successResponse($data, 200, "Get user films success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getWishlistFollowByUserID(Request $request)
    {
        try {
            $films = $request->user()
                ->films()
                ->getQuery()
                ->select('films.id as id', 'films.*', 'user_film.is_follow', 'user_film.views')
                ->where('user_film.is_follow', true);

            $data = $this->getApiFilm($request, $films, 'user_film');

            return $this->successResponse($data, 200, "Get user followed films success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getWishlistViewedByUserID(Request $request)
    {
        try {
            $films = $request->user()
                ->films()
                ->getQuery()
                ->select('films.id as id', 'films.*', 'user_film.is_follow', 'user_film.views')
                ->where('user_film.views', '>', 0);

            $data = $this->getApiFilm($request, $films, 'user_film', 'views');

            return $this->successResponse($data, 200, "Get user viewed films success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getWishlistDetailByUserID(Request $request, int $filmId)
    {
        try {
            $film = Film::query()
                ->with(['status', 'type', 'genres', 'countries', 'episodes'])
                ->where('films.id', $filmId)
                ->where('films.is_delete', 0)
                ->first();

            if (! $film) {
                return $this->errorResponse(404, 'Film not found.');
            }

            $userFilm = UserFilm::query()
                ->where('user_id', $request->user()->id)
                ->where('film_id', $filmId)
                ->first();

            $data = $this->formatFilm($film, [
                "status"        => $film->status->name,
                "type"          => $film->type->name,
                "genres"        => $film->genres->makeHidden('pivot'),
                "countries"     => $film->countries->makeHidden('pivot'),
                "episodes"      => $film->episodes,
                "is_follow"     => (bool) ($userFilm?->is_follow ?? false),
                "is_view"       => (int) ($userFilm?->views ?? 0) > 0,
                "views"         => (int) ($userFilm?->views ?? 0),
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
                'films.server',
            );

            $data = $this->getApiFilm($request, $films, '', 'views');

            return $this->successResponse($data, 200, "Get movie films success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getUpdatedFilmsByUser(Request $request) {
        try {
            $films = $request->user()
                ->films()
                ->getQuery()
                ->select(
                    'films.id',
                    'films.name',
                    'films.slug',
                    'films.poster_url',
                    'films.server',
                )
                ->where(function ($query) {
                    $query->whereBetween('films.updated_at', [Carbon::today(), Carbon::now()])
                        ->orWhere('user_film.is_follow', true);
                });

            $data = $this->getApiFilm($request, $films, 'user_film', 'is_follow');

            return $this->successResponse($data, 200, "Get user films success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function saveUserFilm(Request $request, int $filmId)
    {
        $validated = $request->validate([
            'viewed' => ['sometimes', 'boolean'],
            'followed' => ['sometimes', 'boolean'],
        ]);

        try {
            $film = Film::query()->where('is_delete', 0)->find($filmId);

            if (!$film) {
                return $this->errorResponse(404, "Film not found.");
            }

            $userFilm = UserFilm::query()->firstOrCreate([
                'user_id' => $request->user()->id,
                'film_id' => $filmId,
            ], [
                'views' => 0,
                'is_follow' => false,
            ]);

            if (($validated['viewed'] ?? false) === true) {
                $userFilm->increment('views');
            }

            if (array_key_exists('followed', $validated)) {
                $userFilm->update(['is_follow' => $validated['followed']]);
            }

            $userFilm->refresh();

            return $this->successResponse($userFilm, 200, "Save user film success.");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }
}
