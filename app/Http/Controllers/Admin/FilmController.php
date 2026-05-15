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
use Illuminate\Support\Str;
use App\Http\Requests\FilmRequest;
use App\Models\Type;
use App\Models\Status;
use App\Models\Genre;
use App\Models\Country;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\File;

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

    public function create() {
        $types = Type::query()->orderBy('name')->get();
        $statuses = Status::query()->orderBy('name')->get();
        $genres = Genre::query()->orderBy('name')->get();
        $countries = Country::query()->orderBy('name')->get();

        return view('admin.film.create', compact('types', 'statuses', 'genres', 'countries'));
    }

    public function store(Request $request) {
        $data = $request->validate([
            'name' => 'required|string',
            'origin_name' => 'nullable|string',
            'total_ep' => 'nullable|numeric',
            'time' => 'nullable|string',
            'countries' => 'nullable|string',
            'year' => 'nullable|numeric',
            'quality' => 'nullable|string',
            'slug' => 'nullable|string',
            'poster' => 'nullable|image|max:5120',
            'thumbnail' => 'nullable|image|max:5120',
        ]);

        $film = Film::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'origin_name' => $data['origin_name'] ?? $data['name'],
            'server' => '',
            'description' => '',
            'quality' => $data['quality'] ?? '',
            'time' => $data['time'] ?? '',
            'episode_total' => $data['total_ep'] ?? null,
            'year' => $data['year'] ?? null,
            'status_id' => 1,
            'type_id' => 1,
        ]);

        // handle uploads
        if ($request->hasFile('poster')) {
            $file = $request->file('poster');
            $name = time() . '_poster_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads'), $name);
            $film->poster = $name;
        }

        if ($request->hasFile('thumbnail')) {
            $file = $request->file('thumbnail');
            $name = time() . '_thumb_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads'), $name);
            $film->thumbnail = $name;
        }

        $film->save();

        return redirect()->route('admin.film.edit', ['id' => $film->id]);
    }

    public function update(Request $request, $id) {
        $film = Film::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string',
            'origin_name' => 'nullable|string',
            'total_ep' => 'nullable|numeric',
            'time' => 'nullable|string',
            'countries' => 'nullable|string',
            'year' => 'nullable|numeric',
            'quality' => 'nullable|string',
            'slug' => 'nullable|string',
            'poster' => 'nullable|image|max:5120',
            'thumbnail' => 'nullable|image|max:5120',
            'status_id' => 'nullable|integer',
            'type_id' => 'nullable|integer',
        ]);

        $film->name = $data['name'];
        $film->origin_name = $data['origin_name'] ?? $data['name'];
        $film->slug = $data['slug'] ?? Str::slug($data['name']);
        $film->quality = $data['quality'] ?? $film->quality;
        $film->time = $data['time'] ?? $film->time;
        $film->episode_total = $data['total_ep'] ?? $film->episode_total;
        $film->year = $data['year'] ?? $film->year;

        if (!empty($data['status_id'])) $film->status_id = $data['status_id'];
        if (!empty($data['type_id'])) $film->type_id = $data['type_id'];

        // handle remove flags first
        if ($request->input('remove_poster') == '1') {
            if ($film->poster && File::exists(public_path('uploads/' . $film->poster))) {
                File::delete(public_path('uploads/' . $film->poster));
            }
            $film->poster = null;
        }

        if ($request->input('remove_thumbnail') == '1') {
            if ($film->thumbnail && File::exists(public_path('uploads/' . $film->thumbnail))) {
                File::delete(public_path('uploads/' . $film->thumbnail));
            }
            $film->thumbnail = null;
        }

        // poster replacement
        if ($request->hasFile('poster')) {
            // delete old file
            if ($film->poster && File::exists(public_path('uploads/' . $film->poster))) {
                File::delete(public_path('uploads/' . $film->poster));
            }
            $file = $request->file('poster');
            $name = time() . '_poster_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads'), $name);
            $film->poster = $name;
        }

        // thumbnail replacement
        if ($request->hasFile('thumbnail')) {
            if ($film->thumbnail && File::exists(public_path('uploads/' . $film->thumbnail))) {
                File::delete(public_path('uploads/' . $film->thumbnail));
            }
            $file = $request->file('thumbnail');
            $name = time() . '_thumb_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads'), $name);
            $film->thumbnail = $name;
        }

        $film->save();

        return redirect()->back();
    }

    public function edit($id) {
        $film = Film::query()->where('id', $id)->with(['genres', 'countries', 'status', 'type'])->first();

        $types = Type::query()->orderBy('name')->get();
        $statuses = Status::query()->orderBy('name')->get();
        $genres = Genre::query()->orderBy('name')->get();
        $countries = Country::query()->orderBy('name')->get();

        $selectedGenres = $film->genres->pluck('id')->toArray();
        $selectedCountries = $film->countries->pluck('id')->toArray();

        return view('admin.film.edit', compact('film', 'types', 'statuses', 'genres', 'countries', 'selectedGenres', 'selectedCountries'));
    }
}
