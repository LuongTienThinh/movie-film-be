<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait FullTextSearchTrait
{
    public function toLowerCaseNonAccentVietnamese($str) {
        $str = mb_strtolower($str, 'UTF-8');
    
        $str = preg_replace("/[àáạảãâầấậẩẫăằắặẳẵ]/u", "a", $str);
        $str = preg_replace("/[èéẹẻẽêềếệểễ]/u", "e", $str);
        $str = preg_replace("/[ìíịỉĩ]/u", "i", $str);
        $str = preg_replace("/[òóọỏõôồốộổỗơờớợởỡ]/u", "o", $str);
        $str = preg_replace("/[ùúụủũưừứựửữ]/u", "u", $str);
        $str = preg_replace("/[ỳýỵỷỹ]/u", "y", $str);
        $str = preg_replace("/[đ]/u", "d", $str);
    
        $str = preg_replace('/[^\w\s]/u', '', $str);

        return $str;
    }

    protected function fullTextWildcards($term)
    {
        $reservedSymbols = ['-', '+', '<', '>', '@', '(', ')', '~'];
        $term = str_replace($reservedSymbols, '', $term);

        $words = explode(' ', $term);

        foreach ($words as $key => $word) {
            if (strlen($word) >= 1) {
                $words[$key] = '+' . $word . '+';
            }
        }

        $searchTerm = implode(' ', $words);
        $searchTerm = $this->toLowerCaseNonAccentVietnamese($searchTerm);

        return $searchTerm;
    }

    public function scopeFullTextSearch($query, $columns, $term)
    {
        $columns = is_array($columns) ? implode(", ", $columns) : $columns;
        $query->whereRaw("MATCH ({$columns}) AGAINST (? IN BOOLEAN MODE)", $this->fullTextWildcards($term));

        return $query;
    }
}