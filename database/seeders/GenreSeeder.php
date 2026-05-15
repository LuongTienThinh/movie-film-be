<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Models\Genre;

class GenreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = base_path('data/genres.json');
        $jsonData = File::get($filePath);

        $data = json_decode($jsonData);

        foreach ($data as $value) {
            Genre::create([
                'name' => $value->name,
                'slug' => $value->slug,
            ]);
        }
    }
}
