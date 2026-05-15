<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Film;
use Meilisearch\Client;
use Illuminate\Support\Str;

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
            $client->createIndex('films', ['primaryKey' => 'id']);
            $index = $client->index('films');
        }

        // Use appropriate filterable attributes (facets) and include
        // unaccented variants as searchable attributes to improve
        // matching for languages like Vietnamese.
        $index->updateFilterableAttributes([
            'year',
            'slug',
        ]);

        $index->updateSearchableAttributes([
            'name',
            'origin_name',
            'name_unaccent',
            'origin_name_unaccent',
        ]);

        Film::where('is_delete', 0)
            ->select('id', 'name', 'origin_name', 'slug', 'year')
            ->chunk(500, function ($films) use ($index) {
                $docs = $films->map(function ($f) {
                    return [
                        'id' => $f->id,
                        'name' => $f->name,
                        'origin_name' => $f->origin_name,
                        'slug' => $f->slug,
                        'year' => $f->year,
                        'name_unaccent' => Str::ascii($f->name ?? ''),
                        'origin_name_unaccent' => Str::ascii($f->origin_name ?? ''),
                    ];
                })->toArray();

                $index->addDocuments($docs);
            });

        $this->info('✅ Sync films to Meilisearch done');
    }
}
