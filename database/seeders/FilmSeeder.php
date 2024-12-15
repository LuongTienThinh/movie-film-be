<?php

namespace Database\Seeders;

use App\Models\Film;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use DB;

class FilmSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = base_path('data/films.json');
        $jsonData = File::get($filePath);

        $data = json_decode($jsonData);

        $uploadFolderPath = config('app.url') . '/public/uploads';
        $thumbnailFolderPath = $uploadFolderPath . '/thumbnails';
        $posterFolderPath = $uploadFolderPath . '/posters';

        foreach ($data as $index => $value) {
            $film = $value->movie;
            $episodes = $value->episodes;

            $created_at = Carbon::parse($film->created->time);
            $updated_at = Carbon::parse($film->modified->time);

            $newFilm = Film::create([
                "name" => $film->name,
                "slug" => $film->slug,
                "origin_name" => $film->origin_name,
                "description" => $film->content,
                "quality" => $film->quality,
                "poster_url" => $posterFolderPath . '/' . basename($film->poster_url, '.jpg') . '.webp',
                "thumbnail_url" => $thumbnailFolderPath . '/' . basename($film->thumb_url, '.jpg') . '.webp',
                "trailer_url" => $film->trailer_url,
                "time" => $film->time,
                "episode_current" => $film->episode_current,
                "episode_total" => $film->episode_total,
                "year" => $film->year,
                "status_id" => $film->status,
                "type_id" => $film->type,
                "is_delete" => false,
                "created_at" => $created_at,
                "updated_at" => $updated_at,
            ]);

            foreach ($film->genres as $genre) {
                DB::table("film_genre")->insert([
                    "film_id" => $newFilm->id,
                    "genre_id" => $genre,
                    "created_at" => $created_at,
                    "updated_at" => $updated_at,
                ]);
            }

            foreach ($film->countries as $country) {
                DB::table("country_film")->insert([
                    "film_id" => $newFilm->id,
                    "country_id" => $country,
                    "created_at" => $created_at,
                    "updated_at" => $updated_at,
                ]);
            }

            foreach ($episodes as $ep) {
                DB::table("episodes")->insert([
                    "film_id" => $newFilm->id,
                    "title" => $ep->title,
                    "name" => $ep->name,
                    "slug" => $ep->slug,
                    "link" => $ep->link,
                    "created_at" => $created_at,
                    "updated_at" => $updated_at,
                ]);
            }

            dump(round($index / count($data) * 100, 2) . '%');
        }
    }
}
