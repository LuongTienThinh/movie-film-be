<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

    public function index(Request $request) {
        $page = $request->page ?? 1;
        $perPage = $request->perPage ?? 10;

        $films = Film::query()->take($perPage)->offset(($page - 1) * $perPage)->with(['genres', 'countries'])->orderBy('created_at', 'desc')->get();
        $totalFilms = Film::count();

        $lastPage = ceil($totalFilms / $perPage);

        $pagination = [
            'page'        => $page,
            'perPage'     => $perPage,
            'total_films' => $totalFilms,
            'last_page'   => $lastPage,
            'is_prev'     => $page > 1,
            'is_next'     => $page < $lastPage,
        ];

        if ($request->ajax()) {
            return view('admin.film.table', compact('films', 'pagination'))->render();
        }
        
        return view('admin.film.index', compact('films', 'pagination'));
    }

    public function edit($id) {
        $film = Film::query()->where('id', $id)->first();
        return view('admin.film.edit', compact('film'));
    }
}
