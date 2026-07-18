<?php

namespace App\Services\FilmSources;

class AnimapperSource implements FilmSourceInterface
{
    protected $base = 'https://api.animapper.net/api/v1/metadata';

    protected function getData(string $url): array
    {
        $response = @file_get_contents($url);

        return json_decode($response, true) ?: [];
    }

    public function getPagination(): array
    {
        // Animapper API is metadata-first; no public pagination endpoint available here.
        return [
            'total' => 0,
            'films' => 0,
            'perPage' => 0,
            'currentPage' => 0,
        ];
    }

    public function getItemsByPage(int $page): array
    {
        // Not supported for animapper — return empty list.
        return [];
    }

    public function getDetail(string $slug): array
    {
        // Accept slug as an id for animapper (numeric or string id)
        $id = $slug;

        $url = $this->base . '?id=' . urlencode($id);
        $data = $this->getData($url);
        $res = $data['result'] ?? $data;

        // Map basic movie fields into the shape expected by the pipeline
        $titles = $res['titles'] ?? [];
        $images = $res['images'] ?? [];

        $movie = [];
        $movie['name'] = $titles['user-preferred'] ?? $titles['en'] ?? $titles['main'] ?? ($res['titles']['ja'] ?? null);
        $movie['slug'] = (string) ($res['externalIds']['ANILIST'] ?? $id);
        $movie['server'] = 'animapper';
        $movie['origin_name'] = $titles['main'] ?? null;
        $movie['content'] = is_array($res['descriptions']) ? ($res['descriptions']['en'] ?? '') : ($res['descriptions'] ?? '');
        $movie['poster_url'] = $images['coverLg'] ?? $images['coverMd'] ?? '';
        $movie['thumb_url'] = $images['coverMd'] ?? $images['coverSm'] ?? $movie['poster_url'];
        $movie['trailer_url'] = isset($res['trailer']['site'], $res['trailer']['trailerId']) && strtolower($res['trailer']['site']) === 'youtube'
            ? 'https://www.youtube.com/watch?v=' . $res['trailer']['trailerId']
            : ($res['trailer']['trailerId'] ?? '');

        $createdAt = isset($res['createdAt']) ? (int) floor($res['createdAt'] / 1000) : null;
        $updatedAt = isset($res['updatedAt']) ? (int) floor($res['updatedAt'] / 1000) : null;

        $movie['created'] = ['time' => $createdAt ? date('Y-m-d H:i:s', $createdAt) : null];
        $movie['modified'] = ['time' => $updatedAt ? date('Y-m-d H:i:s', $updatedAt) : null];

        $movie['episode_total'] = isset($res['totalUnits']) ? (int) $res['totalUnits'] : 0;
        $movie['episode_current'] = $movie['episode_total'];
        $movie['year'] = $res['seasonYear'] ?? null;
        $movie['status'] = isset($res['status']) ? strtolower($res['status']) : null;

        // Fetch episode list from stream endpoint if possible
        $episodes = [];
        $streamUrl = 'https://api.animapper.net/api/v1/stream/episodes?id=' . urlencode($id) . '&provider=ANIMEVIETSUB';
        $epsData = $this->getData($streamUrl);

        $mapped = [];
        if (!empty($epsData['episodes']) && is_array($epsData['episodes'])) {
            foreach ($epsData['episodes'] as $ep) {
                $epNum = $ep['episodeNumber'] ?? null;
                $episodeId = $ep['episodeId'] ?? '';

                $name = $epNum;
                $slugEp = 'ep-' . preg_replace('/[^0-9]/', '', $epNum);
                $mapped[] = [
                    'name' => $name,
                    'slug' => $slugEp,
                    'filename' => ($movie['name'] ?? '') . '-' . $name,
                    'link_embed' => $episodeId,
                ];
            }
        }

        $episodes = [
            [
                'server_data' => $mapped,
            ]
        ];

        return [
            'movie' => $movie,
            'episodes' => $episodes,
        ];
    }

    public function getStreamSource(string $episodeData, string $provider, ?string $server = null): array
    {
        $params = 'episodeData=' . urlencode($episodeData) . '&provider=' . urlencode($provider);
        if ($server) {
            $params .= '&server=' . urlencode($server);
        }

        $url = 'https://api.animapper.net/api/v1/stream/source?' . $params;
        $data = $this->getData($url);

        // If response contains 'url' directly, return as-is. Otherwise try to normalize.
        if (isset($data['url']) || isset($data['server'])) {
            return $data;
        }

        return [];
    }
}
