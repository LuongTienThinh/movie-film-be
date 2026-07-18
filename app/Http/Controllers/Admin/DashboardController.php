<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Film;
use App\Models\Genre;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'films' => Film::query()->where('is_delete', false)->count(),
            'genres' => Genre::query()->count(),
            'countries' => Country::query()->count(),
            'users' => User::query()->count(),
        ];

        $latestFilms = Film::query()
            ->where('is_delete', false)
            ->latest('updated_at')
            ->limit(8)
            ->get(['id', 'name', 'origin_name', 'poster_url', 'year', 'updated_at']);

        return view('admin.dashboard', compact('stats', 'latestFilms'));
    }
}
