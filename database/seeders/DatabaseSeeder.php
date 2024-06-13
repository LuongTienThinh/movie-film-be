<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \App\Models\User::truncate();

        \App\Models\User::factory()->create([
            'name' => 'Anime top',
            'email' => 'animetop@gmail.com',
            'password' => Hash::make('Animetop#123'),
        ]);

        $this->call(CountrySeeder::class);
        $this->call(GenreSeeder::class);
        $this->call(TypeSeeder::class);
        $this->call(StatusSeeder::class);
        $this->call(FilmSeeder::class);
    }
}
