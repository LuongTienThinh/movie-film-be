<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\Film;
use App\Models\Country;
use App\Models\Genre;
use App\Models\Status;
use App\Models\Type;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

use App\Jobs\ImportFilmBatch;
use App\Services\FilmSources\FilmSourceInterface;

class CronJobUpdateFilms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cron-job-update-films {--queue : Dispatch batches to queue instead of processing directly inline} {--queue-name=film-updates : The queue name to dispatch jobs to} {--reverse : Fetch data starting from the oldest pages backwards}';

    protected $pages = 20;
    /**
     * Map server key to adapter class
     * @var array
     */
    protected $serverAdapters = [
        'kkphim' => \App\Services\FilmSources\KkPhimSource::class,
        'ophim'  => \App\Services\FilmSources\OphimSource::class,
        // Animapper has no list endpoint yet, so it cannot participate in scheduled imports.
    ];

    /**
     * Return an adapter instance for a given server key
     * @param string $svName
     * @return FilmSourceInterface
     */
    protected function getAdapter(string $svName): FilmSourceInterface
    {
        if (!isset($this->serverAdapters[$svName])) {
            throw new \Exception("Adapter for {$svName} not configured");
        }

        $class = $this->serverAdapters[$svName];

        return new $class();
    }

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch films from source adapters and save in batches of 100 films';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $useQueue = (bool) $this->option('queue');
        $queueName = (string) ($this->option('queue-name') ?: 'film-updates');

        foreach (array_keys($this->serverAdapters) as $name) {
            $data = $this->formatData($name);
            $total = count($data);

            if ($total === 0) {
                continue;
            }

            $chunks = array_chunk($data, 100);
            $this->info("Processing {$total} films from {$name} in " . count($chunks) . " batch(es) (100 films/batch)...");

            foreach ($chunks as $index => $chunk) {
                if ($useQueue) {
                    ImportFilmBatch::dispatch($chunk)->onQueue($queueName);
                    $this->info(" -> Dispatched batch #" . ($index + 1) . " (" . count($chunk) . " films) to queue '{$queueName}'");
                } else {
                    (new ImportFilmBatch($chunk))->handle();
                    $this->info(" -> Saved batch #" . ($index + 1) . " (" . count($chunk) . " films) to database");
                }
            }
        }

        $this->info('Reindexing Meilisearch...');

        Artisan::call('meili:sync-films', [
            '--fresh' => true,
        ]);

        $this->info('Meilisearch reindex done');
    }


    public function toLowerCaseNonAccentVietnamese($str) {
        $str = mb_strtolower($str, 'UTF-8');
    
        $str = preg_replace("/[àáạảãâầấậẩẫăằắặẳẵ]/u", "a", $str);
        $str = preg_replace("/[èéẹẻẽêềếệểễ]/u", "e", $str);
        $str = preg_replace("/[ìíịỉĩ]/u", "i", $str);
        $str = preg_replace("/[òóọỏõôồốộổỗơờớợởỡ]/u", "o", $str);
        $str = preg_replace("/[ùúụủũưừứựửữ]/u", "u", $str);
        $str = preg_replace("/[ỳýỵỷỹ]/u", "y", $str);
        $str = preg_replace("/[đ]/u", "d", $str);
    
        $str = preg_replace("/[\u0300\u0301\u0303\u0309\u0323]/u", "", $str);
        $str = preg_replace("/[\u02C6\u0306\u031B]/u", "", $str);
    
        return $str;
    }
    
    public function toSlug($str) {
        return str_replace(' ', '-', $this->toLowerCaseNonAccentVietnamese($str));
    }
    
    public function getDetailFilm($svName, $slug) {    
        $adapter = $this->getAdapter($svName);

        return $adapter->getDetail($slug);
    }
    
    public function getAnimePagination($svName) {    
        $adapter = $this->getAdapter($svName);

        return $adapter->getPagination();
    }
    
    public function getAnimeByPage($svName, $page) {    
        $adapter = $this->getAdapter($svName);

        return $adapter->getItemsByPage($page);
    }
    
    public function getAnime($svName) {
        $paginations = $this->getAnimePagination($svName);
        $data = [];
    
        for ($page = 1; $page <= $paginations['total']; $page++) {
            $pageData = $this->getAnimeByPage($svName, $page);
            $data = array_merge($data, $pageData);
        }
    
        return $data;
    }
    
    public function getAnimeDetail($svName, $page = 1) {
        $paginations = $this->getAnimePagination($svName);
        $total = (int) ($paginations['total'] ?? 0);

        if ($total < 1) {
            return [];
        }

        $reverse = (bool) $this->option('reverse');
        $maxPages = min($this->pages, $total);

        if ($reverse) {
            // Reverse mode: e.g. total=500, pages=30 => 500, 499, 498, ..., 471
            $startPage = $total;
            $endPage = max(1, $total - $maxPages + 1);
            $pageList = range($startPage, $endPage);
            $this->info("Running in REVERSE mode for {$svName}: fetching pages {$startPage} down to {$endPage} (oldest {$maxPages} pages)");
        } else {
            // Normal mode: 1, 2, ..., maxPages
            $pageList = range(1, $maxPages);
            $this->info("Running in NORMAL mode for {$svName}: fetching pages 1 to {$maxPages}");
        }

        $result = [];

        foreach ($pageList as $p) {
            $pageData = $this->fetchSinglePageDetail($svName, $p);
            $result = array_merge($result, $pageData);
        }

        return $result;
    }

    public function fetchSinglePageDetail($svName, $page) {
        $slugList = $this->getAnimeByPage($svName, $page);
        $result = [];
    
        try {
            foreach ($slugList as $index => $slug) {
                $start_time = microtime(true);
                $data = $this->getDetailFilm($svName, $slug);
                $end_time1 = microtime(true);
    
                $result[$index] = [
                    'movie' => $data['movie'] ?? [],
                    'episodes' => array_map(function($f) {
                        return [
                            'name'  => $f['name'],
                            'slug'  => $f['slug'],
                            'title' => $f['filename'] ?? ($f['name'] ?? ''),
                            'link'  => $f['link_embed'] ?: ''
                        ];
                    }, $data['episodes'][0]['server_data'] ?? []),
                ];
                $end_time2 = microtime(true);
    
                $updated_at = isset($result[$index]['movie']['modified']['time'])
                    ? Carbon::parse($result[$index]['movie']['modified']['time'])->format('Y-m-d H:i:s')
                    : now()->format('Y-m-d H:i:s');
                $end_time3 = microtime(true);
    
                $film = Film::where('slug', $slug)->where('server', $svName)->first();
                $end_time4 = microtime(true);

                if ($film) {
                    Episode::where('film_id', $film->id)->where('slug', '')->delete();
                    $end_time5 = microtime(true);

                    $uploadFolderPath = config('app.url') . '/public/uploads';
                    $saveUploadFolderPath = public_path('uploads');

                    $imageFolderPath = [
                        'posters'               => $uploadFolderPath . '/posters',
                        'thumbnails'            => $uploadFolderPath . '/thumbnails',
                        'posters-compress'      => $saveUploadFolderPath . '/posters',
                        'thumbnails-compress'   => $saveUploadFolderPath . '/thumbnails',
                        'posters-need-compress' => $saveUploadFolderPath . '/posters-need-compress',
                        'thumbnails-need-compress' => $saveUploadFolderPath . '/thumbnails-need-compress',
                    ];

                    $posterUrl      = $svName == 'kkphim' ? ($result[$index]['movie']['poster_url'] ?? '') : ($result[$index]['movie']['thumb_url'] ?? '');
                    $thumbnailUrl   = $svName == 'kkphim' ? ($result[$index]['movie']['thumb_url'] ?? '') : ($result[$index]['movie']['poster_url'] ?? '');
                    echo $posterUrl;

                    $localPoster    = $imageFolderPath['posters'] . '/' . pathinfo($posterUrl, PATHINFO_FILENAME) . '.webp';
                    $localThumbnail = $imageFolderPath['thumbnails'] . '/' . pathinfo($thumbnailUrl, PATHINFO_FILENAME) . '.webp';

                    if (! empty($posterUrl) && ! $this->isExistInFolder($localPoster)) {
                        echo $this->downloadImage($posterUrl, $imageFolderPath['posters-compress'] . '/' . pathinfo($posterUrl, PATHINFO_FILENAME) . '.webp') . "\n";
                        echo $this->downloadImage($posterUrl, $imageFolderPath['posters-need-compress'] . '/' . basename($posterUrl)) . "\n";
                    }

                    if (! empty($thumbnailUrl) && ! $this->isExistInFolder($localThumbnail)) {
                        echo $this->downloadImage($thumbnailUrl, $imageFolderPath['thumbnails-compress'] . '/' . pathinfo($thumbnailUrl, PATHINFO_FILENAME) . '.webp') . "\n";
                        echo $this->downloadImage($thumbnailUrl, $imageFolderPath['thumbnails-need-compress'] . '/' . basename($thumbnailUrl)) . "\n";
                    }
                    $end_time6 = microtime(true);

                    if (Carbon::parse($film->updated_at)->format('Y-m-d H:i:s') === $updated_at && $film->slug === $slug && $film->server === $svName) {
                        unset($result[$index]);
                    } else {
                        $episode_current = 0;
                        $lastEpisode = end($result[$index]['episodes']);
                        if ($lastEpisode && preg_match('/\d+/', $lastEpisode['slug'], $matches)) {
                            $episode_current = (int)$matches[0];
                        }
                        
                        $film->updated_at       = $updated_at;
                        $film->origin_name      = $result[$index]['movie']['origin_name'] ?? '';
                        $film->description      = $result[$index]['movie']['content'] ?? '';
                        $film->episode_total    = (int) ($result[$index]['movie']['episode_total'] ?? 0);
                        $film->episode_current  = $episode_current;
                        $film->year             = $result[$index]['movie']['year'] ?? 0;
                        $statusId               = Status::where('slug', $result[$index]['movie']['status'] ?? '')->value('id');
                        if ($statusId) {
                            $film->status_id = $statusId;
                        }
                        $film->trailer_url      = $result[$index]['movie']['trailer_url'] ?? '';
                        $film->save();
    
                        foreach ($result[$index]['episodes'] as $ep) {
                            $existingEpisode = Episode::where('film_id', $film->id)
                                                      ->where('slug', $ep['slug'])
                                                      ->first();
                    
                            $currentTime = Carbon::now();
                    
                            if (!$existingEpisode) {
                                try {
                                    $episode = Episode::create([
                                        'film_id' => $film->id,
                                        'title' => $ep['title'],
                                        'name' => $ep['name'],
                                        'slug' => $ep['slug'],
                                        'link' => $ep['link'] ?? '',
                                        'created_at' => $currentTime,
                                        'updated_at' => $currentTime,
                                    ]);
                                } catch (\Throwable $e) {
                                    echo 'Error: ' . $e->getMessage();
                                }
                            } else {
                                $existingEpisode->title = $ep['title'];
                                $existingEpisode->name = $ep['name'];
                                $existingEpisode->slug = $ep['slug'];
                                $existingEpisode->link = $ep['link'];
                                $existingEpisode->save();
                            }
                        }
                        unset($result[$index]);
                    }
                    $end_time7 = microtime(true);
                }

                echo "\nReading page {$page} slug: " . ($index + 1) . " - $slug\n";
                echo "Time 1: " . ($end_time1 - $start_time) . "\n";
                echo "Time 2: " . ($end_time2 - $end_time1) . "\n";
                echo "Time 3: " . ($end_time3 - $end_time2) . "\n";
                echo "Time 4: " . ($end_time4 - $end_time3) . "\n";
                if ($film) {
                    echo "Time 5: " . ($end_time5 - $end_time4) . "\n";
                    echo "Time 6: " . ($end_time6 - $end_time5) . "\n";
                    echo "Time 7: " . ($end_time7 - $end_time6) . "\n";
                }
            }
    
            return array_values($result);
    
        } catch (\Throwable $e) {
            $this->error('Unable to fetch page ' . $page . ' from ' . $svName . ': ' . $e->getMessage());
            return [];
        }
    }
    
    public function setTypes($svName) {
        $data = $this->getAnimeDetail($svName);
    
        if ($svName == 'nguonc') {
            foreach ($data as &$e) {
                if (isset($e['movie']['category']['1']['list'][0]['name']) && $e['movie']['category']['1']['list'][0]['name'] == 'Phim bộ') {
                    $e['movie']['type'] = 'series';
                } else {
                    $e['movie']['type'] = 'movies';
                }
            }
        } else {
            foreach ($data as &$e) {
                if (isset($e['movie']['episode_total']) && intval($e['movie']['episode_total']) == 1) {
                    $e['movie']['type'] = 'movies';
                } else {
                    $e['movie']['type'] = 'series';
                }
            }
        }
    
        return $data;
    }
    
    public function removeUnused($svName) {
        $data = $this->setTypes($svName);
    
        foreach ($data as &$e) {
            $movie = $e['movie'];

            unset(
                $movie['casts'],
                $movie['id'],
                $movie['_id'],
                $movie['is_copyright'],
                $movie['sub_docquyen'],
                $movie['chieurap'],
                $movie['notify'],
                $movie['showtimes'],
                $movie['actor'],
                $movie['director']
            );
    
            $e['movie'] = $movie;
        }
    
        return $data;
    }
    
    public function editTypes($svName) {
        $data = $this->removeUnused($svName);
        $types = Type::query()->pluck('id', 'slug');
    
        foreach ($data as &$e) {
            $type = $e['movie']['type'];
            unset($e['movie']['type']);
    
            $e['movie']['type'] = $types->get($type);
        }
    
        return $data;
    }
    
    public function editCountries($svName) {
        $data = $this->editTypes($svName);
        $countries = Country::query()->pluck('id', 'slug');
        $newData = [];
    
        if ($svName == 'nguonc') {
            $result = array_filter($data, function (&$e) use ($countries) {
                $e['movie']['countries'] = array_filter(
                    $e['movie']['category']['4']['list'] ?? [],
                    function (&$ct) use ($countries) {
                        $slug = $this->toSlug($ct['name']);
                        $ct['slug'] = $slug;
    
                        $countryId = $countries->get($slug);
                        if ($countryId) {
                            $ct['id'] = $countryId;
                            return true;
                        }
    
                        return false;
                    }
                );
    
                $e['movie']['countries'] = array_column($e['movie']['countries'], 'id');
    
                return count($e['movie']['countries']) == count($e['movie']['category']['4']['list'] ?? []);
            });
    
            return array_values($result);
        } else {
            foreach ($data as $key => &$value) {
                $countryList = $value['movie']['country'] ?? [];
                unset($value['movie']['country']);
    
                $value['movie']['countries'] = [];
    
                foreach ($countryList as &$ct) {
                    $countryId = $countries->get($ct['slug']);
    
                    if ($countryId) {
                        $ct['id'] = $countryId;
                        $value['movie']['countries'][] = $ct;
                    }
                }
    
                if (count($value['movie']['countries']) == 0) {
                    array_splice($data, $key, 1);
                } else {
                    $value['movie']['countries'] = array_column($value['movie']['countries'], 'id');
                    $newData[] = $value;
                }
            }
            
            return $newData;
        }
    }
    
    public function editGenres($svName) {
        $data = $this->editCountries($svName);
        $genres = Genre::query()->pluck('id', 'slug');
        $newData = [];
    
        if ($svName == 'nguonc') {
            $result = array_filter($data, function ($e) use ($genres) {
                $isAnime = false;
                $e['movie']['genres'] = array_filter($e['movie']['category']['2']['list'], function ($cate) use ($genres, &$isAnime) {
                    $slug = $this->toSlug($cate['name']);
                    $cate['slug'] = $slug;
    
                    if ($slug == 'hoat-hinh') {
                        $isAnime = true;
                    }
    
                    $genreId = $genres->get($cate['slug']);
                    if ($genreId) {
                        $cate['id'] = $genreId;
                        return true;
                    }
    
                    return false;
                });
    
                $e['movie']['genres'] = array_map(function ($g) {
                    return $g['id'];
                }, $e['movie']['genres']);
    
                return count($e['movie']['genres']) > 0 && $isAnime;
            });
    
            return $result;
        } else {
            foreach ($data as $key => &$value) {
                $countryList = $value['movie']['category'] ?? [];
                unset($value['movie']['category']);
    
                $value['movie']['genres'] = [];
    
                foreach ($countryList as &$ct) {
                    $genreId = $genres->get($ct['slug']);
    
                    if ($genreId) {
                        $ct['id'] = $genreId;
                        $value['movie']['genres'][] = $ct;
                    }
                }
    
                if (count($value['movie']['genres']) == 0) {
                    array_splice($data, $key, 1);
                } else {
                    $value['movie']['genres'] = array_column($value['movie']['genres'], 'id');
                    $newData[] = $value;
                }
            }
            
            return $newData;
        }
    }
    
    public function formatData($svName) {
        $data = $this->editGenres($svName);
        $statuses = Status::query()->pluck('id', 'slug');
    
        if ($svName == 'nguonc') {
            foreach ($data as &$e) {
                $episode_current = 0;
                if (isset($e['movie']['episodes'][0]['items'])) {
                    if (preg_match('/\d+/', $e['movie']['episodes'][0]['items'][count($e['movie']['episodes'][0]['items']) - 1]['slug'], $matches)) {
                        $episode_current = (int)$matches[0];
                    }
                }

                $e['movie']['origin_name']      = $e['movie']['original_name'];
                $e['movie']['server']           = $svName;
                $e['movie']['created']          = ['time' => $e['movie']['created']];
                $e['movie']['modified']         = ['time' => $e['movie']['modified']];
                $e['movie']['content']          = $e['movie']['description'];
                $e['movie']['episode_total']    = (int) $e['movie']['total_episodes'] ?: 0;
                $e['movie']['episode_current']  = $episode_current;
                $e['movie']['year']             = isset($e['movie']['category']['3']['list'][0]['name']) ? $e['movie']['category']['3']['list'][0]['name'] : null;
                $e['movie']['view']             = $e['movie']['view'] ?: 0;
                $e['movie']['status']           = isset($e['movie']['category']['1']['list']) && in_array('Phim đang chiếu', array_column($e['movie']['category']['1']['list'], 'name')) ? 1 : 2;
                $e['movie']['trailer_url']      = '';
    
                list($e['movie']['thumb_url'], $e['movie']['poster_url']) = [$e['movie']['poster_url'], $e['movie']['thumb_url']];
    
                $e['episodes'] = isset($e['movie']['episodes'][0]['items']) ? array_map(function($f) use ($e) {
                    return [
                        'name'  => $f['name'],
                        'slug'  => $f['slug'],
                        'title' => $e['movie']['name'] . '-' . $f['name'],
                        'link'  => $f['embed'] ?: ''
                    ];
                }, $e['movie']['episodes'][0]['items']) : [];
    
                unset(
                    $e['movie']['original_name'], 
                    $e['movie']['total_episodes'], 
                    $e['movie']['current_episode'], 
                    $e['movie']['category'], 
                    $e['movie']['description'], 
                    $e['movie']['episodes']
                );
            }
        } else {
            foreach ($data as &$e) {
                $episode_current = 0;
                if (isset($e['episodes'])) {
                    if (preg_match('/\d+/', $e['episodes'][count($e['episodes']) - 1]['slug'], $matches)) {
                        $episode_current = (int)$matches[0];
                    }
                }
                
                $e['movie']['server']           = $svName;
                $e['movie']['episode_total']    = (int) $e['movie']['episode_total'] ?? 0;
                $e['movie']['episode_current']  = $episode_current ?: 0;
                
                $e['movie']['status']           = $statuses->get($e['movie']['status']);
    
                if ($svName == 'ophim') {
                    list($e['movie']['thumb_url'], $e['movie']['poster_url']) = [$e['movie']['poster_url'], $e['movie']['thumb_url']];
                }

                foreach (['thumb_url', 'poster_url'] as $urlKey) {
                    if (! empty($e['movie'][$urlKey]) && ! str_starts_with($e['movie'][$urlKey], 'http')) {
                        $e['movie'][$urlKey] = 'https://img.phimapi.com/upload/vod/' . ltrim($e['movie'][$urlKey], '/');
                    }
                }
            }
        }
    
        return $data;
    }

    function isExistInFolder($url) {
        $imageData = @getimagesize($url);
        return $imageData !== false;
    }

    function downloadImage($imageUrl, $saveTo) {
        $dir = dirname($saveTo);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
    
        $imageContent = @file_get_contents($imageUrl);
    
        if ($imageContent === false) {
            return "Không thể tải nội dung từ URL.";
        }
    
        $saved = @file_put_contents($saveTo, $imageContent);
    
        if ($saved === false) {
            return "Không thể lưu file.";
        }
    
        return "Hình ảnh đã được tải về thành công: " . $saveTo;
    }
}
