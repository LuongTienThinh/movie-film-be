<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = base_path('data/status.json');
        $jsonData = File::get($filePath);

        $data = json_decode($jsonData);

        foreach ($data as $value) {
            Status::create([
                'name' => $value->name,
                'slug' => $value->slug,
            ]);
        }
    }
}
