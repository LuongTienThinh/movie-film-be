<?php

namespace App\Services\FilmSources;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KkPhimSource implements FilmSourceInterface
{
    protected $url = 'https://phimapi.com/v1/api/danh-sach/hoat-hinh';
    protected $urlDetail = 'https://phimapi.com/phim';

    protected function getData(string $url): array
    {
        try {
            $response = Http::acceptJson()->timeout(10)->retry(2, 200)->get($url);
            if (! $response->successful()) {
                Log::warning('KkPhim request failed', ['url' => $url, 'status' => $response->status()]);
                return [];
            }

            return $response->json() ?: [];
        } catch (\Throwable $e) {
            Log::warning('KkPhim request failed', ['url' => $url, 'message' => $e->getMessage()]);
            return [];
        }
    }

    public function getPagination(): array
    {
        $data = $this->getData($this->url);
        $pagination = $data['data']['params']['pagination'] ?? [];

        return [
            'total' => $pagination['totalPages'] ?? 0,
            'films' => $pagination['totalItems'] ?? 0,
            'perPage' => $pagination['totalItemsPerPage'] ?? 0,
            'currentPage' => $pagination['currentPage'] ?? 1,
        ];
    }

    public function getItemsByPage(int $page): array
    {
        $url = $this->url . '?page=' . $page;
        $data = $this->getData($url);

        $items = $data['data']['items'] ?? [];

        return array_values(array_filter(array_column($items, 'slug')));
    }

    public function getDetail(string $slug): array
    {
        $url = $this->urlDetail . '/' . $slug;

        return $this->getData($url);
    }

}
