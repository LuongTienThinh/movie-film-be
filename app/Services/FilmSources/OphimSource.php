<?php

namespace App\Services\FilmSources;

class OphimSource implements FilmSourceInterface
{
    protected $url = 'https://ophim1.com/v1/api/danh-sach/hoat-hinh';
    protected $urlDetail = 'https://ophim1.com/phim';

    protected function getData(string $url): array
    {
        $response = @file_get_contents($url);

        return json_decode($response, true) ?: [];
    }

    public function getPagination(): array
    {
        $data = $this->getData($this->url);
        $pagination = $data['data']['params']['pagination'] ?? [];

        $total = 0;
        if (!empty($pagination['totalItems']) && !empty($pagination['totalItemsPerPage'])) {
            $total = (int) ceil($pagination['totalItems'] / $pagination['totalItemsPerPage']);
        }

        return [
            'total' => $total,
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

        return array_map(function ($e) {
            return $e['slug'] ?? null;
        }, array_values($items));
    }

    public function getDetail(string $slug): array
    {
        $url = $this->urlDetail . '/' . $slug;

        return $this->getData($url);
    }

    public function getStreamSource(string $episodeData, string $provider, ?string $server = null): array
    {
        // Not supported for ophim adapter in this refactor — return empty structure
        return [
            'server' => null,
            'type' => null,
            'corsProxyRequired' => false,
            'proxyHeaders' => null,
            'url' => null,
        ];
    }
}
