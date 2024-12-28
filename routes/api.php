<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\FilmController;
use App\Http\Controllers\GenreController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('user')->middleware('auth:sanctum')->group(function () {
    Route::get('/', function (Request $request) {
        return $request->user();
    });

    Route::get('/theme', [UserController::class, 'getThemeMode']);
    Route::put('/update-theme', [UserController::class, 'updateThemeMode']);
});

Route::prefix('auth')->group(function () {
    Route::post('/sign-up', [AuthController::class, 'register'])->name('api_sign-up');
    Route::post('/login', [AuthController::class, 'login'])->name('api_login');
    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class,'logout'])->name('api_logout');
});

Route::prefix('film')->group(function () {
    Route::get('/detail', [FilmController::class, 'getFilmDetail'])->name('api_film_detail');
    Route::get('/latest', [FilmController::class, 'getLatestFilm'])->name('api_latest_film');
    Route::get('/series', [FilmController::class, 'getSeriesFilms'])->name('api_series_film');
    Route::get('/movies', [FilmController::class, 'getMovieFilms'])->name('api_movies_film');
    Route::get('/search', [FilmController::class,'getFilmBySearch'])->name('api_search_film');
    Route::get('/genre/{slug}', [FilmController::class,'getFilmByGenre'])->name('api_genre_film');
    Route::get('/country/{slug}', [FilmController::class,'getFilmByCountry'])->name('api_country_film');
    
    Route::prefix('wishlist')->group(function () {
        Route::get('/{userId}', [FilmController::class, 'getWishlistByUserID'])->name('api_wishlist_user_film');
        Route::get('/{userId}/{filmId}', [FilmController::class, 'getWishlistDetailByUserID'])->name('api_wishlist_user_film_detail');
        Route::put('/{userId}/{filmId}', [FilmController::class, 'saveUserFilm'])->name('api_save_wishlist_user_film_detail');
    });
});

Route::prefix('category')->group(function () {
    Route::prefix('genres')->group(function () {
        Route::get('/', [GenreController::class, 'getAllGenres'])->name('api_list_genre');
        Route::get('/{slug}', [GenreController::class,'getGenreDetail'])->name('api_genre_detail');
    });

    Route::prefix('countries')->group(function () {
        Route::get('/', [CountryController::class, 'getAllCountries'])->name('api_list_country');
        Route::get('/{slug}', [CountryController::class, 'getCountryDetail'])->name('api_country_detail');
    });
});