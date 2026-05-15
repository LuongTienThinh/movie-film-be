<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Film;
use Meilisearch\Client;

class SyncFilmsToMeili extends Command
{
    protected $signature = 'meili:sync-films {--fresh}';
    protected $description = 'Sync films table to Meilisearch';

    public function handle()
    {
        $client = new Client(
            config('services.meilisearch.host'),
            config('services.meilisearch.key')
        );

        $index = $client->index('films');

        if ($this->option('fresh')) {
            $this->info('🔄 Reset index films');
            $client->deleteIndex('films');
            $index = $client->createIndex('films', ['primaryKey' => 'id']);
        }

        Film::where('is_delete', 0)
            ->select('id', 'name', 'origin_name', 'slug', 'year')
            ->chunk(500, function ($films) use ($index) {
                $index->addDocuments($films->toArray());
            });

        $this->info('✅ Sync films to Meilisearch done');
    }
}
