<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\Film;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CronJobUpdateFilms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cron-job-update-films';

    protected $server = [
        'kkphim' => [
            'url' => 'https://phimapi.com/v1/api/danh-sach/hoat-hinh',
            'urlDetail' => 'https://phimapi.com/phim'
        ],
        'ophim' => [
            'url' => 'https://ophim1.com/v1/api/danh-sach/hoat-hinh',
            'urlDetail' => 'https://ophim1.com/phim'
        ],
        // 'nguonc' => [
        //     'url' => 'https://phim.nguonc.com/api/films/quoc-gia/nhat-ban',
        //     'urlDetail' => 'https://phim.nguonc.com/api/film'
        // ],
    ];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Giả sử $this->server là một mảng dữ liệu bạn muốn xử lý
        foreach ($this->server as $name => $value) {
            $data = $this->formatData($name);

            $uploadFolderPath = config('app.url') . '/public/uploads';
            $thumbnailFolderPath = $uploadFolderPath . '/thumbnails';
            $posterFolderPath = $uploadFolderPath . '/posters';
            
            DB::beginTransaction();
            try {
                foreach ($data as $index => $value) {
                    $film = $value['movie'];
                    $episodes = $value['episodes'];

                    $created_at = Carbon::parse($film['created']['time']);
                    $updated_at = Carbon::parse($film['modified']['time']);

                    $newFilm = Film::create([
                        "name"              => $film['name'],
                        "slug"              => $film['slug'],
                        "server"            => $film['server'],
                        "origin_name"       => $film['origin_name'] ?? '',
                        "description"       => $film['content'] ?? '',
                        "quality"           => $film['quality'],
                        "poster_url"        => $posterFolderPath . '/' . basename($film['poster_url'], '.jpg') . '.webp',
                        "thumbnail_url"     => $thumbnailFolderPath . '/' . basename($film['thumb_url'], '.jpg') . '.webp',
                        "trailer_url"       => $film['trailer_url'],
                        "time"              => $film['time'],
                        "episode_current"   => $film['episode_current'],
                        "episode_total"     => $film['episode_total'],
                        "year"              => $film['year'] ?? 0,
                        "status_id"         => $film['status'],
                        "type_id"           => $film['type'],
                        "is_delete"         => false,
                        "created_at"        => $created_at,
                        "updated_at"        => $updated_at,
                    ]);

                    $filmGenres = [];
                    foreach ($film['genres'] as $genre) {
                        $filmGenres[] = [
                            "film_id"       => $newFilm['id'],
                            "genre_id"      => $genre,
                            "created_at"    => $created_at,
                            "updated_at"    => $updated_at,
                        ];
                    }
                    DB::table("film_genre")->insert($filmGenres);

                    $filmCountries = [];
                    foreach ($film['countries'] as $country) {
                        $filmCountries[] = [
                            "film_id"       => $newFilm['id'],
                            "country_id"    => $country,
                            "created_at"    => $created_at,
                            "updated_at"    => $updated_at,
                        ];
                    }
                    DB::table("country_film")->insert($filmCountries);

                    $episodesData = [];
                    foreach ($episodes as $ep) {
                        $episodesData[] = [
                            "film_id"       => $newFilm['id'],
                            "title"         => $ep['title'],
                            "name"          => $ep['name'],
                            "slug"          => $ep['slug'],
                            "link"          => $ep['link'] ?? '',
                            "created_at"    => $created_at,
                            "updated_at"    => $updated_at,
                        ];
                    }
                    DB::table("episodes")->insert($episodesData);

                    echo "\r" . str_repeat(' ', 100);
                    echo "\r Insert new data: " . round($index / count($data) * 100, 2) . '%';
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                echo "Lỗi: " . $e->getMessage();
            }
        }
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
    
    public function getData($url) {
        $response = file_get_contents($url);
    
        return json_decode($response, true);
    }
    
    public function getDetailFilm($svName, $slug) {    
        $url = $this->server[$svName]['urlDetail'] . '/' . $slug;
        $data = $this->getData($url);
    
        return $data;
    }
    
    public function getAnimePagination($svName) {    
        $data = $this->getData($this->server[$svName]['url']);
    
        $pagination = ($svName === 'nguonc') ? $data['paginate'] : $data['data']['params']['pagination'];
    
        if ($svName === 'nguonc') {
            return [
                'total' => $pagination['total_page'],
                'films' => $pagination['total_items'],
                'perPage' => $pagination['items_per_page'],
                'currentPage' => $pagination['current_page'],
            ];
        } else {
            return [
                'total' => ($svName === 'kkphim') 
                    ? $pagination['totalPages'] 
                    : ceil($pagination['totalItems'] / $pagination['totalItemsPerPage']),
                'films' => $pagination['totalItems'],
                'perPage' => $pagination['totalItemsPerPage'],
                'currentPage' => $pagination['currentPage'],
            ];
        }
    }
    
    public function getAnimeByPage($svName, $page) {    
        $url = $this->server[$svName]['url'] . '?page=' . $page;
        $data = $this->getData($url);
    
        $items = ($svName === 'nguonc' ? $data : $data['data'])['items'];
    
        $newData = array_map(function($e) {
            return $e['slug'];
        }, $items);
    
        return $newData;
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
        global $connect;
    
        $paginations = $this->getAnimePagination($svName);
        $slugList = array_reverse($this->getAnimeByPage($svName, $page));
        $result = [];
    
        try {
            $flag = false;
            foreach ($slugList as $index => $slug) {
                $data = $this->getDetailFilm($svName, $slug);
    
                $result[$index] = [
                    'movie' => $data['movie'] ?? [],
                    'episodes' => array_map(function($f) {
                        return [
                            'name'  => $f['name'],
                            'slug'  => $f['slug'],
                            'title' => $f['filename'],
                            'link'  => $f['link_embed'] ?: ''
                        ];
                    }, $data['episodes'][0]['server_data']) ?? [],
                ];
    
                $updated_at = Carbon::parse($result[$index]['movie']['modified']['time'])->format('Y-m-d H:i:s');
    
                $film = Film::where('slug', $slug)->where('server', $svName)->first();
    
                if ($film) {
                    if ($film['updated_at'] === $updated_at && $film['slug'] === $slug && $film['server'] === $svName) {
                        $flag = true;
                        break;
                    } else {
                        $film->updated_at = $updated_at;
                        $film->save();
    
                        foreach ($result[$index]['episodes'] as $ep) {
                            $existingEpisode = Episode::where('film_id', $film->id)
                                                      ->where('slug', $ep['slug'])
                                                      ->first();
                    
                            $currentTime = Carbon::now();
                    
                            if (!$existingEpisode) {
                                Episode::create([
                                    'film_id' => $film->id,
                                    'title' => $ep['title'],
                                    'name' => $ep['name'],
                                    'slug' => $ep['slug'],
                                    'link' => $ep['link'],
                                    'created_at' => $currentTime,
                                    'updated_at' => $currentTime,
                                ]);
                            }
                        }
                    }
                    unset($result[$index]);
                }

                echo "\r" . str_repeat(' ', 100);
                echo "\r Reading slug: " . ($page - 1) * count($slugList) + $index + 1;
            }
    
            if (!$flag && $page < $paginations['total']) {
                $page = $page + 1;
                $result = array_values(array_merge($this->getAnimeDetail($svName, $page), $result));
            }
    
            return $result;
    
        } catch (\Exception $e) {
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
        $types = json_decode(file_get_contents(base_path('/data') . "/$svName/types.json"), true);
    
        foreach ($data as &$e) {
            $type = $e['movie']['type'];
            unset($e['movie']['type']);
    
            $index = array_search($type, array_column($types, 'slug'));
    
            $e['movie']['type'] = $index !== false ? $index + 1 : null;
        }
    
        return $data;
    }
    
    public function editCountries($svName) {
        $data = $this->editTypes($svName);
        $countries = json_decode(file_get_contents(base_path('/data')  . "/$svName/countries.json"), true);
    
        if ($svName == 'nguonc') {
            $result = array_filter($data, function (&$e) use ($countries) {
                $e['movie']['countries'] = array_filter(
                    $e['movie']['category']['4']['list'] ?? [],
                    function (&$ct) use ($countries) {
                        $slug = toSlug($ct['name']);
                        $ct['slug'] = $slug;
    
                        $cIndex = array_search($slug, array_column($countries, 'slug'));
                        if ($cIndex !== false) {
                            $ct['id'] = $cIndex + 1;
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
                    $cIndex = array_search($ct['slug'], array_column($countries, 'slug'));
    
                    if ($cIndex !== false) {
                        $ct['id'] = $cIndex + 1;
                        $value['movie']['countries'][] = $ct;
                    }
                }
    
                if (count($value['movie']['countries']) == 0) {
                    array_splice($data, $key, 1);
                } else {
                    $value['movie']['countries'] = array_column($value['movie']['countries'], 'id');
                }
            }
            
            return $data;
        }
    }
    
    public function editGenres($svName) {
        $data = $this->editCountries($svName);
        $genres = json_decode(file_get_contents(base_path('/data') . "/$svName/genres.json"), true);
    
        if ($svName == 'nguonc') {
            $result = array_filter($data, function ($e) use ($genres) {
                $isAnime = false;
                $e['movie']['genres'] = array_filter($e['movie']['category']['2']['list'], function ($cate) use ($genres, &$isAnime) {
                    $slug = toSlug($cate['name']);
                    $cate['slug'] = $slug;
    
                    if ($slug == 'hoat-hinh') {
                        $isAnime = true;
                    }
    
                    $cIndex = array_search($cate['slug'], array_column($genres, 'slug'));
                    if ($cIndex !== false) {
                        $cate['id'] = $cIndex + 1;
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
                    $gIndex = array_search($ct['slug'], array_column($genres, 'slug'));
    
                    if ($gIndex !== false) {
                        $ct['id'] = $gIndex + 1;
                        $value['movie']['genres'][] = $ct;
                    }
                }
    
                if (count($value['movie']['genres']) == 0) {
                    array_splice($data, $key, 1);
                } else {
                    $value['movie']['genres'] = array_column($value['movie']['genres'], 'id');
                }
            }
            
            return $data;
        }
    }
    
    public function formatData($svName) {
        $data = $this->editGenres($svName);
        $statuses = json_decode(file_get_contents(base_path('/data') . "/$svName/status.json"), true);
    
        if ($svName == 'nguonc') {
            foreach ($data as &$e) {
                $e['movie']['origin_name']      = $e['movie']['original_name'];
                $e['movie']['server']           = $svName;
                $e['movie']['created']          = ['time' => $e['movie']['created']];
                $e['movie']['modified']         = ['time' => $e['movie']['modified']];
                $e['movie']['content']          = $e['movie']['description'];
                $e['movie']['episode_total']    = (int) $e['movie']['total_episodes'] ?: 0;
                $e['movie']['episode_current']  = isset($e['movie']['episodes'][0]['items']) ? count($e['movie']['episodes'][0]['items']) : 0;
                $e['movie']['year']             = isset($e['movie']['category']['3']['list'][0]['name']) ? $e['movie']['category']['3']['list'][0]['name'] : null;
                $e['movie']['view']             = $e['movie']['view'] ?: 0;
                $e['movie']['status']           = isset($e['movie']['category']['1']['list']) && in_array('Phim đang chiếu', array_column($e['movie']['category']['1']['list'], 'name')) ? 1 : 2;
                $e['movie']['trailer_url']      = '';
    
                list($e['movie']['thumb_url'], $e['movie']['poster_url']) = [$e['movie']['poster_url'], $e['movie']['thumb_url']];
    
                $e['episodes'] = isset($e['movie']['episodes'][0]['items']) ? array_map(function($f) use ($e) {
                    return [
                        'name' => $f['name'],
                        'slug' => $f['slug'],
                        'title' => $e['movie']['name'] . '-' . $f['name'],
                        'link' => $f['embed'] ?: ''
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
                $e['movie']['server']           = $svName;
                $e['movie']['episode_total']    = (int)$e['movie']['episode_total'] ?: 0;
                $e['movie']['episode_current']  = isset($e['episodes'][0]['server_data']) ? count($e['episodes'][0]['server_data']) : 0;
                $e['movie']['status']           = array_search($e['movie']['status'], array_column($statuses, 'slug')) + 1;
    
                if ($svName == 'ophim') {
                    list($e['movie']['thumb_url'], $e['movie']['poster_url']) = [$e['movie']['poster_url'], $e['movie']['thumb_url']];
                }
            }
        }
    
        return $data;
    }
}
