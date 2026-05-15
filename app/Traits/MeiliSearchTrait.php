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

        // Meilisearch index = tên table
        $indexName = $query->getModel()->getTable();

        $result = $this->meili()
            ->index($indexName)
            ->search($term, [
                'limit' => 50,
            ]);

        $ids = collect($result['hits'])
            ->pluck('id')
            ->toArray();

        if (empty($ids)) {
            // Không có kết quả
            return $query->whereRaw('1 = 0');
        }

        // Giữ đúng thứ tự ranking của Meili
        return $query
            ->whereIn('id', $ids)
            ->orderByRaw('FIELD(id,'.implode(',', $ids).')');
    }
}
