<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = base_path('data/countries.json');
        $jsonData = File::get($filePath);

        $data = json_decode($jsonData);

        foreach ($data as $value) {
            Country::create([
                'name' => $value->name,
                'slug' => $value->slug,
            ]);
        }
    }
}
