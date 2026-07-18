<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\FilmController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
Route::domain(env('ADMIN_DOMAIN'))->group(function () {
    Route::prefix('user')->middleware('auth:sanctum')->group(function () {
        Route::get('/', function (Request $request) {
            return $request->user();
        });
    
        Route::get('/theme', [UserController::class, 'getThemeMode']);
        Route::put('/update-theme', [UserController::class, 'updateThemeMode']);
        Route::put('/update-email', [UserController::class, 'updateEmail']);
        Route::put('/update-phone', [UserController::class, 'updatePhone']);
        Route::put('/update-password', [UserController::class, 'updatePassword']);
    });
    
    Route::prefix('auth')->group(function () {
        Route::post('/sign-up', [AuthController::class, 'register'])->name('api_sign-up');
        Route::post('/login', [AuthController::class, 'login'])->name('api_login');
        Route::middleware('web')->group(function () {
            Route::get('/google/redirect', [AuthController::class, 'googleRedirect'])->name('api_google_redirect');
            Route::get('/google/callback', [AuthController::class, 'googleCallback'])->name('api_google_callback');
            Route::get('/facebook/redirect', [AuthController::class, 'facebookRedirect'])->name('api_facebook_redirect');
            Route::get('/facebook/callback', [AuthController::class, 'facebookCallback'])->name('api_facebook_callback');
        });
        Route::post('/oauth/exchange', [AuthController::class, 'exchangeOAuthCode'])->name('api_oauth_exchange');
        Route::middleware('auth:sanctum')->post('/logout', [AuthController::class,'logout'])->name('api_logout');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('api_forgot_password');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('api_reset_password');
    });
    
    Route::prefix('film')->group(function () {
        Route::get('/detail', [FilmController::class, 'getFilmDetail'])->name('api_film_detail');
        Route::get('/filter', [FilmController::class, 'getFilteredFilms'])->name('api_filter_film');
        Route::get('/latest', [FilmController::class, 'getLatestFilm'])->name('api_latest_film');
        Route::get('/series', [FilmController::class, 'getSeriesFilms'])->name('api_series_film');
        Route::get('/movies', [FilmController::class, 'getMovieFilms'])->name('api_movies_film');
        Route::get('/top-views', [FilmController::class, 'getTopViewedFilms'])->name('api_top_views_film');
        Route::get('/search', [FilmController::class,'getFilmBySearch'])->name('api_search_film');
        Route::get('/genre/{slug}', [FilmController::class,'getFilmByGenre'])->name('api_genre_film');
        Route::get('/country/{slug}', [FilmController::class,'getFilmByCountry'])->name('api_country_film');
        
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/updated', [FilmController::class,'getUpdatedFilmsByUser'])->name('api_updated_user_film');

            Route::prefix('wishlist')->group(function () {
                Route::get('/', [FilmController::class, 'getWishlistByUserID'])->name('api_wishlist_user_film');
                Route::get('/follow', [FilmController::class, 'getWishlistFollowByUserID'])->name('api_wishlist_user_film_follow');
                Route::get('/viewed', [FilmController::class, 'getWishlistViewedByUserID'])->name('api_wishlist_user_film_viewed');
                Route::get('/{filmId}', [FilmController::class, 'getWishlistDetailByUserID'])->whereNumber('filmId')->name('api_wishlist_user_film_detail');
                Route::put('/{filmId}', [FilmController::class, 'saveUserFilm'])->whereNumber('filmId')->name('api_save_wishlist_user_film_detail');
            });
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
});
