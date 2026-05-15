<?php

namespace App\Traits;

use Meilisearch\Client;

trait FullTextSearchTrait
{
    protected function meili(): Client
    {
        return new Client(
            config('services.meilisearch.host'),
            config('services.meilisearch.key')
        );
    }

    public function scopeFullTextSearch($query, $columns, string $term)
    {
        if (empty($term)) {
            return $query;
        }

        $indexName = $query->getModel()->getTable();

        $result = $this->meili()
            ->index($indexName)
            ->search($term, [
                'matchingStrategy' => 'all',
                'limit' => 50,
                'attributesToRetrieve' => ['id'],
            ]);

        $ids = collect($result->getHits())
            ->pluck('id')
            ->toArray();

        if (empty($ids)) {
            return $query->whereRaw('1 = 0');
        }

        $table = $query->getModel()->getTable();

        return $query
            ->whereIn("$table.id", $ids)
            ->orderByRaw('FIELD('.$table.'.id,'.implode(',', $ids).')');
    }
}
