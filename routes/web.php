<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CountryController;
use App\Http\Controllers\Admin\FilmController;
use App\Http\Controllers\Admin\GenreController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\UserController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::domain(env('ADMIN_DOMAIN'))->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginAdmin'])->name('login');
    Route::post('/login', [AuthController::class, 'loginAdmin'])
        ->middleware('throttle:6,1')
        ->name('admin.login.submit');

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::post('/logout', [AuthController::class, 'logoutAdmin'])->name('admin.logout');

        Route::prefix('film')->group(function() {
            Route::get('/management', [FilmController::class, 'index'])->name('admin.film.management');
            Route::get('/create', [FilmController::class, 'create'])->name('admin.film.create');
            Route::post('/store', [FilmController::class, 'store'])->name('admin.film.store');
            Route::get('/edit/{id}', [FilmController::class, 'edit'])->name('admin.film.edit');
            Route::put('/update/{id}', [FilmController::class, 'update'])->name('admin.film.update');
            Route::delete('/delete/{id}', [FilmController::class, 'delete'])->name('admin.film.delete');
        });

        Route::resource('genres', GenreController::class)
            ->except('show')
            ->names([
                'index' => 'admin.genres.index',
                'create' => 'admin.genres.create',
                'store' => 'admin.genres.store',
                'edit' => 'admin.genres.edit',
                'update' => 'admin.genres.update',
                'destroy' => 'admin.genres.destroy',
            ]);
        Route::resource('countries', CountryController::class)
            ->except('show')
            ->names([
                'index' => 'admin.countries.index',
                'create' => 'admin.countries.create',
                'store' => 'admin.countries.store',
                'edit' => 'admin.countries.edit',
                'update' => 'admin.countries.update',
                'destroy' => 'admin.countries.destroy',
            ]);

        Route::get('/system-information', [SystemController::class, 'index'])
            ->name('admin.system.info');

        Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
        Route::get('/users/{id}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
        Route::put('/users/{id}', [UserController::class, 'update'])->name('admin.users.update');
    });
});
