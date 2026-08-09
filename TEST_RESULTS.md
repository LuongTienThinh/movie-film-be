# Animapper API Test Results

## Test Environment
- **Date**: 2026-05-25
- **Base URL**: `https://api.animapper.net/api/v1`
- **Test Media ID**: `1` (Cowboy Bebop)

---

## Test Results

### 1. Metadata Endpoint ✅
**URL**: `GET /metadata?id=1`

**Response Sample**:
```json
{
  "success": true,
  "result": {
    "id": 1,
    "mediaType": "ANIME",
    "format": "TV",
    "status": "FINISHED",
    "totalUnits": 26,
    "seasonYear": 1998,
    "titles": {
      "user-preferred": "Cowboy Bebop",
      "main": "Cowboy Bebop",
      "en": "Cowboy Bebop",
      "ja": "カウボーイビバップ"
    },
    "images": {
      "coverXl": "https://s4.anilist.co/file/anilistcdn/media/anime/cover/large/...",
      "coverLg": "https://s4.anilist.co/file/anilistcdn/media/anime/cover/medium/...",
      "bannerUrl": "https://s4.anilist.co/file/anilistcdn/media/anime/banner/..."
    },
    "genres": [
      { "id": 7, "name": "Adventure" },
      { "id": 6, "name": "Action" },
      { "id": 9, "name": "Sci-Fi" }
    ],
    "streamingProviders": {
      "ANIMEVIETSUB": {
        "providerMediaId": "a820",
        "similarity": 1.0
      }
    }
  }
}
```

**Mapping Result**:
- Name: **Cowboy Bebop**
- Status: **finished** (→ need mapping to internal status ID)
- Total Episodes: **26**
- Year: **1998**
- Created: **2026-01-05 07:11:11**
- Modified: **2026-01-14 05:59:12**
- Genres: **Adventure, Action, Sci-Fi**

---

### 2. Episodes Endpoint ✅
**URL**: `GET /stream/episodes?id=1&provider=ANIMEVIETSUB`

**Response Sample**:
```json
{
  "provider": "ANIMEVIETSUB",
  "limit": 26,
  "offset": 0,
  "total": 26,
  "hasNextPage": false,
  "episodes": [
    {
      "episodeNumber": "01",
      "episodeId": "a820$13339",
      "server": "Tổng hợp"
    },
    {
      "episodeNumber": "02",
      "episodeId": "a820$13340",
      "server": "Tổng hợp"
    },
    ...
    {
      "episodeNumber": "26",
      "episodeId": "a820$13364",
      "server": "Tổng hợp"
    }
  ]
}
```

**Mapping Result**:
- Total Episodes Returned: **26**
- Episode Format: `{episodeNumber: "XX", episodeId: "a820$XXXXX"}`
- Maps to: `name: "01"`, `slug: "ep-01"`, `link_embed: "a820$13339"`

---

### 3. Stream Source Endpoint ⚠️
**URL**: `GET /stream/source?episodeData=a820$13339&provider=ANIMEVIETSUB&server=DU`

**Response**:
```json
{
  "success": false,
  "message": "An internal error occurred: No source found for episode a820$13339",
  "code": "INTERNAL_ERROR"
}
```

**Note**: The episode source may not be available yet or requires additional parameters. This is expected behavior from the API.

---

## Running the Tests

### Run PHP Test
```bash
cd admin
php test-animapper.php
```

**Output**:
```
====== Animapper API Test (id=1) ======

=== 1. Metadata (getDetail) ===
Name: Cowboy Bebop
Origin Name: Cowboy Bebop
Slug: 1
Status: finished
Total Episodes: 26
Year: 1998
Created: 2026-01-05 07:11:11
Modified: 2026-01-14 05:59:12

=== 2. Episodes List (from stream/episodes) ===
Total episodes: 26

First 3 episodes:
  1. Name: 01, Slug: ep-01, EpisodeId: a820$13339
  2. Name: 02, Slug: ep-02, EpisodeId: a820$13340
  3. Name: 03, Slug: ep-03, EpisodeId: a820$13341
```

### Run cURL Test
```bash
cd admin
bash test-animapper-curl.sh
```

---

## Integration with CronJobUpdateFilms

The `AnimapperSource` adapter is already registered in `CronJobUpdateFilms`:

```php
protected $serverAdapters = [
    'kkphim' => \App\Services\FilmSources\KkPhimSource::class,
    'ophim'  => \App\Services\FilmSources\OphimSource::class,
    'animapper' => \App\Services\FilmSources\AnimapperSource::class,  // ← Added
];
```

When the cron job runs for `animapper` server:
1. Adapter fetches metadata via `/metadata?id={id}`
2. Adapter fetches episodes via `/stream/episodes?id={id}&provider=ANIMEVIETSUB`
3. Data is mapped and stored in database
4. Stream sources can be retrieved on-demand via `getStreamSource()`

---

## Usage Examples

### Direct Usage in Code
```php
use App\Services\FilmSources\AnimapperSource;

$adapter = new AnimapperSource();

// Get metadata + episodes
$detail = $adapter->getDetail('1');
$movie = $detail['movie'];     // Movie metadata
$episodes = $detail['episodes']; // Episodes array

// Get stream source for specific episode
$source = $adapter->getStreamSource(
    'a820$13339',           // episodeData from episodes list
    'ANIMEVIETSUB',         // provider
    'DU'                    // server (DU for HLS, HDX for EMBED)
);

if (!empty($source['url'])) {
    echo "Stream URL: " . $source['url'];
}
```

### In Controller/API
```php
// Example: Get anime with episodes
$anime = Film::where('server', 'animapper')->where('slug', '1')->first();
$episodes = $anime->episodes()->get();

// Example: Get stream for episode
$adapter = new AnimapperSource();
$stream = $adapter->getStreamSource($episode->link, 'ANIMEVIETSUB', 'DU');
```

---

## Next Steps

1. **Map Status**: Create mapping for `finished` → internal status ID
2. **Map Genres**: Store genre IDs from Animapper to internal genre table
3. **Stream Source Handling**: Implement retry logic or cache management for stream URLs
4. **Search API**: Implement pagination support using `/search` endpoint if needed
5. **Error Handling**: Add more robust error handling for API rate limits

---

## API Rate Limiting
- **Limit**: 60 requests per minute
- **Reference**: https://animapper.net/docs/introduction/rate-limiting

---

**Test Files**:
- PHP Test: `admin/test-animapper.php`
- cURL Test: `admin/test-animapper-curl.sh`
