<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\Film;
use App\Models\User;
use App\Models\UserFilm;
// use App\Traits\FilmTrait;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use DB;

class AdminController extends Controller
{
    // use FilmTrait;
    use ApiResponseTrait;

    public function dashboard(Request $request) {
        if (!Auth::check()) {
            return redirect()->route('admin.api_login');
        }

        
    }

    public function login() {

    }

    public function logout() {

    }
}
