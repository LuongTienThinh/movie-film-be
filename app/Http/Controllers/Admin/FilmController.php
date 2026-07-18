<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Film;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Type;
use App\Models\Status;
use App\Models\Genre;
use App\Models\Country;
use DB;
use Illuminate\Support\Facades\File;

class FilmController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'genres' => ['sometimes', 'array'],
            'genres.*' => ['integer', 'distinct', 'exists:genres,id'],
            'countries' => ['sometimes', 'array'],
            'countries.*' => ['integer', 'distinct', 'exists:countries,id'],
            'years' => ['sometimes', 'array'],
            'years.*' => ['integer', 'distinct', 'min:1900', 'max:2100'],
        ]);

        $page = $validated['page'] ?? 1;
        $perPage = $validated['perPage'] ?? 10;
        $search = trim($validated['search'] ?? '');
        $selectedGenres = array_map('intval', $validated['genres'] ?? []);
        $selectedCountries = array_map('intval', $validated['countries'] ?? []);
        $selectedYears = array_map('intval', $validated['years'] ?? []);

        $query = Film::query()
            ->where('is_delete', false)
            ->with(['genres', 'countries', 'status', 'type'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('origin_name', 'like', '%' . $search . '%')
                        ->orWhere('slug', 'like', '%' . $search . '%');
                });
            })
            ->when($selectedGenres, function ($query) use ($selectedGenres) {
                $query->whereHas('genres', function ($query) use ($selectedGenres) {
                    $query->whereIn('genres.id', $selectedGenres);
                });
            })
            ->when($selectedCountries, function ($query) use ($selectedCountries) {
                $query->whereHas('countries', function ($query) use ($selectedCountries) {
                    $query->whereIn('countries.id', $selectedCountries);
                });
            })
            ->when($selectedYears, fn ($query) => $query->whereIn('year', $selectedYears));

        $totalFilms = (clone $query)->count();
        $lastPage = max(1, (int) ceil($totalFilms / $perPage));
        $page = min($page, $lastPage);

        $films = (clone $query)
            ->orderByDesc('updated_at')
            ->forPage($page, $perPage)
            ->get();

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

        $genres = Genre::query()->orderBy('name')->get(['id', 'name']);
        $countries = Country::query()->orderBy('name')->get(['id', 'name']);
        $years = Film::query()
            ->where('is_delete', false)
            ->whereBetween('year', [1900, 2100])
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');

        return view('admin.film.index', compact(
            'films',
            'pagination',
            'genres',
            'countries',
            'years',
            'search',
            'selectedGenres',
            'selectedCountries',
            'selectedYears'
        ));
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
            'total_ep' => 'nullable|integer|min:0',
            'time' => 'nullable|string',
            'countries' => 'nullable|array',
            'countries.*' => 'integer|exists:countries,id',
            'genres' => 'nullable|array',
            'genres.*' => 'integer|exists:genres,id',
            'year' => 'nullable|integer|min:1900|max:2100',
            'quality' => 'nullable|string',
            'slug' => 'nullable|string',
            'description' => 'nullable|string',
            'status_id' => 'required|integer|exists:statuses,id',
            'type_id' => 'required|integer|exists:types,id',
            'poster' => 'nullable|image|max:5120',
            'thumbnail' => 'nullable|image|max:5120',
            'episode_name' => 'nullable|array',
            'episode_name.*' => 'nullable|string|max:255',
            'episode_link' => 'nullable|array',
            'episode_link.*' => 'nullable|string',
        ]);

        $film = DB::transaction(function () use ($request, $data) {
            $film = Film::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'origin_name' => $data['origin_name'] ?? $data['name'],
                'server' => 'admin',
                'description' => $data['description'] ?? '',
                'quality' => $data['quality'] ?? '',
                'poster_url' => $this->storeUpload($request, 'poster'),
                'thumbnail_url' => $this->storeUpload($request, 'thumbnail'),
                'trailer_url' => '',
                'time' => $data['time'] ?? '',
                'episode_current' => count(array_filter($data['episode_name'] ?? [])),
                'episode_total' => $data['total_ep'] ?? 0,
                'year' => $data['year'] ?? (int) date('Y'),
                'status_id' => $data['status_id'],
                'type_id' => $data['type_id'],
            ]);

            $film->countries()->sync($data['countries'] ?? []);
            $film->genres()->sync($data['genres'] ?? []);
            $this->syncEpisodes($film, $data['episode_name'] ?? [], $data['episode_link'] ?? []);

            return $film;
        });

        return redirect()->route('admin.film.edit', ['id' => $film->id]);
    }

    public function update(Request $request, $id) {
        $film = Film::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string',
            'origin_name' => 'nullable|string',
            'total_ep' => 'nullable|numeric',
            'time' => 'nullable|string',
            'countries' => 'nullable|array',
            'countries.*' => 'integer|exists:countries,id',
            'genres' => 'nullable|array',
            'genres.*' => 'integer|exists:genres,id',
            'year' => 'nullable|numeric',
            'quality' => 'nullable|string',
            'slug' => 'nullable|string',
            'poster' => 'nullable|image|max:5120',
            'thumbnail' => 'nullable|image|max:5120',
            'status_id' => 'nullable|integer|exists:statuses,id',
            'type_id' => 'nullable|integer|exists:types,id',
            'description' => 'nullable|string',
            'episode_name' => 'nullable|array',
            'episode_link' => 'nullable|array',
        ]);

        $film->name = $data['name'];
        $film->origin_name = $data['origin_name'] ?? $data['name'];
        $film->slug = $data['slug'] ?? Str::slug($data['name']);
        $film->quality = $data['quality'] ?? $film->quality;
        $film->time = $data['time'] ?? $film->time;
        $film->episode_total = $data['total_ep'] ?? $film->episode_total;
        $film->year = $data['year'] ?? $film->year;
        $film->description = $data['description'] ?? $film->description;

        if (!empty($data['status_id'])) $film->status_id = $data['status_id'];
        if (!empty($data['type_id'])) $film->type_id = $data['type_id'];

        // handle remove flags first
        if ($request->input('remove_poster') == '1') {
            $this->deleteUpload($film->poster_url);
            $film->poster_url = '';
        }

        if ($request->input('remove_thumbnail') == '1') {
            $this->deleteUpload($film->thumbnail_url);
            $film->thumbnail_url = '';
        }

        // poster replacement
        if ($request->hasFile('poster')) {
            // delete old file
            $this->deleteUpload($film->poster_url);
            $film->poster_url = $this->storeUpload($request, 'poster');
        }

        // thumbnail replacement
        if ($request->hasFile('thumbnail')) {
            $this->deleteUpload($film->thumbnail_url);
            $film->thumbnail_url = $this->storeUpload($request, 'thumbnail');
        }

        $film->save();
        $film->countries()->sync($data['countries'] ?? []);
        $film->genres()->sync($data['genres'] ?? []);
        $this->syncEpisodes($film, $data['episode_name'] ?? [], $data['episode_link'] ?? []);
        $film->update(['episode_current' => count(array_filter($data['episode_name'] ?? []))]);

        return redirect()->back();
    }

    public function edit($id) {
        $film = Film::query()->where('id', $id)->with(['genres', 'countries', 'status', 'type', 'episodes'])->firstOrFail();

        $types = Type::query()->orderBy('name')->get();
        $statuses = Status::query()->orderBy('name')->get();
        $genres = Genre::query()->orderBy('name')->get();
        $countries = Country::query()->orderBy('name')->get();

        $selectedGenres = $film->genres->pluck('id')->toArray();
        $selectedCountries = $film->countries->pluck('id')->toArray();

        return view('admin.film.edit', compact('film', 'types', 'statuses', 'genres', 'countries', 'selectedGenres', 'selectedCountries'));
    }

    public function delete($id)
    {
        $film = Film::findOrFail($id);
        $film->update(['is_delete' => true]);

        return redirect()->route('admin.film.management');
    }

    private function storeUpload(Request $request, string $field): string
    {
        if (! $request->hasFile($field)) {
            return '';
        }

        $file = $request->file($field);
        $name = time() . '_' . $field . '_' . uniqid() . '.' . $file->extension();
        File::ensureDirectoryExists(public_path('uploads'));
        $file->move(public_path('uploads'), $name);

        return asset('uploads/' . $name);
    }

    private function deleteUpload(?string $url): void
    {
        if (! $url) {
            return;
        }

        $path = public_path('uploads/' . basename(parse_url($url, PHP_URL_PATH)));
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    private function syncEpisodes(Film $film, array $names, array $links): void
    {
        $film->episodes()->delete();
        foreach ($names as $index => $name) {
            if (! trim((string) $name)) {
                continue;
            }

            $film->episodes()->create([
                'title' => $film->name . ' - ' . $name,
                'name' => $name,
                'slug' => Str::slug($name),
                'link' => $links[$index] ?? '',
            ]);
        }
    }
}
