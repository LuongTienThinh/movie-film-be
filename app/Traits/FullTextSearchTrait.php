<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait FullTextSearchTrait
{
    protected function fullTextWildcards($term)
    {
        $reservedSymbols = ['-', '+', '<', '>', '@', '(', ')', '~'];
        $term = str_replace($reservedSymbols, '', $term);

        $words = explode(' ', $term);

        foreach ($words as $key => $word) {
            if (strlen($word) >= 1) {
                $words[$key] = '+' . $word . '*';
            }
        }

        $searchTerm = implode(' ', $words);

        return $searchTerm;
    }

    public function scopeFullTextSearch($query, $columns, $term)
    {
        $columns = is_array($columns) ? implode(", ", $columns) : $columns;
        $query->whereRaw("MATCH ({$columns}) AGAINST (? IN BOOLEAN MODE)", $this->fullTextWildcards($term));

        return $query;
    }
}