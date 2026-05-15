<?php

namespace Database\Seeders;

use App\Models\Type;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class TypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = base_path('data/types.json');
        $jsonData = File::get($filePath);

        $data = json_decode($jsonData);

        foreach ($data as $value) {
            Type::create([
                'name' => $value->name,
                'slug' => $value->slug,
            ]);
        }
    }
}
