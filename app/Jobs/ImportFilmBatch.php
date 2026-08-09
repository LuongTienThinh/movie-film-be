<?php

namespace App\Jobs;

use App\Models\Film;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportFilmBatch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(public array $filmsChunk)
    {
    }

    public function handle(): void
    {
        $count = count($this->filmsChunk);
        $this->log('info', "Started importing batch of {$count} films.");

        $uploadFolderPath = config('app.url') . '/public/uploads';
        $saveUploadFolderPath = public_path('uploads');
        $imageFolderPath = [
            'posters'                  => $uploadFolderPath . '/posters',
            'thumbnails'               => $uploadFolderPath . '/thumbnails',
            'posters-compress'         => $saveUploadFolderPath . '/posters',
            'thumbnails-compress'      => $saveUploadFolderPath . '/thumbnails',
            'posters-need-compress'    => $saveUploadFolderPath . '/posters-need-compress',
            'thumbnails-need-compress' => $saveUploadFolderPath . '/thumbnails-need-compress',
        ];

        foreach ($this->filmsChunk as $value) {
            if (empty($value['movie']['slug'])) {
                continue;
            }

            $film = $value['movie'];
            $episodes = $value['episodes'] ?? [];

            DB::beginTransaction();
            try {
                $created_at = isset($film['created']['time']) ? Carbon::parse($film['created']['time']) : now();
                $updated_at = isset($film['modified']['time']) ? Carbon::parse($film['modified']['time']) : now();

                $posterUrl = $this->resolvePosterOrThumbUrl($film['poster_url'] ?? '', 'posters', $imageFolderPath);
                $thumbnailUrl = $this->resolvePosterOrThumbUrl($film['thumb_url'] ?? '', 'thumbnails', $imageFolderPath);

                $existingFilm = Film::query()->where('slug', $film['slug'])->first();

                if ($existingFilm) {
                    $existingFilm->update([
                        "name"            => $film['name'],
                        "origin_name"     => $film['origin_name'] ?? '',
                        "description"     => $film['content'] ?? '',
                        "quality"         => $film['quality'] ?? 'HD',
                        "poster_url"      => $posterUrl,
                        "thumbnail_url"   => $thumbnailUrl,
                        "trailer_url"     => $film['trailer_url'] ?? '',
                        "time"            => $film['time'] ?? null,
                        "episode_current" => $film['episode_current'] ?? null,
                        "episode_total"   => $film['episode_total'] ?? null,
                        "year"            => $film['year'] ?? 0,
                        "status_id"       => $film['status'],
                        "type_id"         => $film['type'],
                        "updated_at"      => $updated_at,
                    ]);
                    $newFilm = $existingFilm;
                } else {
                    $newFilm = Film::create([
                        "name"            => $film['name'],
                        "slug"            => $film['slug'],
                        "server"          => $film['server'],
                        "origin_name"     => $film['origin_name'] ?? '',
                        "description"     => $film['content'] ?? '',
                        "quality"         => $film['quality'] ?? 'HD',
                        "poster_url"      => $posterUrl,
                        "thumbnail_url"   => $thumbnailUrl,
                        "trailer_url"     => $film['trailer_url'] ?? '',
                        "time"            => $film['time'] ?? null,
                        "episode_current" => $film['episode_current'] ?? null,
                        "episode_total"   => $film['episode_total'] ?? null,
                        "year"            => $film['year'] ?? 0,
                        "status_id"       => $film['status'],
                        "type_id"         => $film['type'],
                        "is_delete"       => false,
                        "created_at"      => $created_at,
                        "updated_at"      => $updated_at,
                    ]);
                }

                if (!empty($film['genres'])) {
                    DB::table("film_genre")->where('film_id', $newFilm->id)->delete();
                    $filmGenres = [];
                    foreach ($film['genres'] as $genre) {
                        $filmGenres[] = [
                            "film_id"    => $newFilm->id,
                            "genre_id"   => $genre,
                            "created_at" => $created_at,
                            "updated_at" => $updated_at,
                        ];
                    }
                    DB::table("film_genre")->insert($filmGenres);
                }

                if (!empty($film['countries'])) {
                    DB::table("country_film")->where('film_id', $newFilm->id)->delete();
                    $filmCountries = [];
                    foreach ($film['countries'] as $country) {
                        $filmCountries[] = [
                            "film_id"    => $newFilm->id,
                            "country_id" => $country,
                            "created_at" => $created_at,
                            "updated_at" => $updated_at,
                        ];
                    }
                    DB::table("country_film")->insert($filmCountries);
                }

                if (!empty($episodes)) {
                    DB::table("episodes")->where('film_id', $newFilm->id)->delete();
                    $episodesData = [];
                    foreach ($episodes as $ep) {
                        $episodesData[] = [
                            "film_id"    => $newFilm->id,
                            "title"      => $ep['title'] ?? '',
                            "name"       => $ep['name'] ?? '',
                            "slug"       => $ep['slug'] ?? '',
                            "link"       => $ep['link'] ?? '',
                            "created_at" => $created_at,
                            "updated_at" => $updated_at,
                        ];
                    }
                    DB::table("episodes")->insert($episodesData);
                }

                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                $this->log('error', "Failed importing film slug '{$film['slug']}': {$e->getMessage()}", [
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $this->log('info', "Successfully completed importing batch of {$count} films.");
    }

    private function log(string $level, string $message, array $context = []): void
    {
        try {
            $logFile = 'film_updates-' . date('Y-m-d') . '.log';
            \Illuminate\Support\Facades\Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/' . $logFile),
            ])->log($level, "[ImportFilmBatch] {$message}", $context);
        } catch (Throwable) {
            \Illuminate\Support\Facades\Log::log($level, "[ImportFilmBatch] {$message}", $context);
        }
    }

    private function resolvePosterOrThumbUrl(string $remoteUrl, string $folderType, array $imageFolderPath): string
    {
        $remoteUrl = trim($remoteUrl);
        if ($remoteUrl === '') {
            return '';
        }

        if (! str_starts_with($remoteUrl, 'http://') && ! str_starts_with($remoteUrl, 'https://')) {
            $remoteUrl = 'https://img.phimapi.com/upload/vod/' . ltrim($remoteUrl, '/');
        }

        $filename = pathinfo(parse_url($remoteUrl, PHP_URL_PATH) ?? '', PATHINFO_FILENAME);
        if ($filename === '') {
            return $remoteUrl;
        }

        $localRelativeFile = 'uploads/' . $folderType . '/' . $filename . '.webp';
        $localFullPath = public_path($localRelativeFile);

        if (! is_file($localFullPath) || filesize($localFullPath) < 1024) {
            $this->downloadImage($remoteUrl, $imageFolderPath["{$folderType}-compress"] . '/' . $filename . '.webp');
            $this->downloadImage($remoteUrl, $imageFolderPath["{$folderType}-need-compress"] . '/' . basename($remoteUrl));
        }

        if (is_file($localFullPath) && filesize($localFullPath) >= 1024) {
            return $imageFolderPath[$folderType] . '/' . $filename . '.webp';
        }

        return $remoteUrl;
    }

    private function downloadImage(string $url, string $savePath): void
    {
        $saveDir = dirname($savePath);
        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0777, true);
        }

        try {
            $ch = curl_init($url);
            $fp = fopen($savePath, 'wb');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_exec($ch);
            curl_close($ch);
            fclose($fp);
        } catch (Throwable $e) {
            // Ignore download errors silently
        }
    }
}
