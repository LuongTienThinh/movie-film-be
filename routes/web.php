<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\FilmController;

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
    Route::middleware('auth')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');

        Route::prefix('film')->group(function() {
            Route::get('/management', [FilmController::class, 'index'])->name('admin.film.management');
            Route::get('/create', [FilmController::class, 'create'])->name('admin.film.create');
            Route::post('/store', [FilmController::class, 'store'])->name('admin.film.store');
            Route::get('/edit/{id}', [FilmController::class, 'edit'])->name('admin.film.edit');
            Route::put('/update/{id}', [FilmController::class, 'update'])->name('admin.film.update');
            Route::delete('/delete/{id}', [FilmController::class, 'delete'])->name('admin.film.delete');
        });
    });

    Route::get('/login', [AuthController::class, 'showLoginAdmin'])->name('login');
    Route::post('/login', [AuthController::class, 'loginAdmin'])->name('admin.login.submit');
});
