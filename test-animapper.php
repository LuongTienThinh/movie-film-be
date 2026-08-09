<?php

/**
 * Test script for Animapper API endpoints
 * Run: php test-animapper.php
 */

require_once __DIR__ . '/app/Services/FilmSources/FilmSourceInterface.php';
require_once __DIR__ . '/app/Services/FilmSources/AnimapperSource.php';

use App\Services\FilmSources\AnimapperSource;

echo "====== Animapper API Test (id=1) ======\n\n";

$adapter = new AnimapperSource();

// Test 1: getDetail
echo "=== 1. Metadata (getDetail) ===\n";
$detail = $adapter->getDetail('1');
$movie = $detail['movie'] ?? [];
echo "Name: " . ($movie['name'] ?? 'N/A') . "\n";
echo "Origin Name: " . ($movie['origin_name'] ?? 'N/A') . "\n";
echo "Slug: " . ($movie['slug'] ?? 'N/A') . "\n";
echo "Status: " . ($movie['status'] ?? 'N/A') . "\n";
echo "Total Episodes: " . ($movie['episode_total'] ?? 'N/A') . "\n";
echo "Year: " . ($movie['year'] ?? 'N/A') . "\n";
echo "Poster URL: " . ($movie['poster_url'] ?? 'N/A') . "\n";
echo "Trailer URL: " . ($movie['trailer_url'] ?? 'N/A') . "\n";
echo "Created: " . ($movie['created']['time'] ?? 'N/A') . "\n";
echo "Modified: " . ($movie['modified']['time'] ?? 'N/A') . "\n";

// Test 2: Episodes
echo "\n=== 2. Episodes List (from stream/episodes) ===\n";
$episodes = $detail['episodes'] ?? [];
if (!empty($episodes[0]['server_data'])) {
    echo "Total episodes: " . count($episodes[0]['server_data']) . "\n";
    echo "\nFirst 3 episodes:\n";
    foreach (array_slice($episodes[0]['server_data'], 0, 3) as $idx => $ep) {
        echo sprintf("  %d. Name: %s, Slug: %s, EpisodeId: %s\n",
            $idx + 1,
            $ep['name'] ?? 'N/A',
            $ep['slug'] ?? 'N/A',
            $ep['link_embed'] ?? 'N/A'
        );
    }
} else {
    echo "No episodes found\n";
}

// Test 3: Stream Source
echo "\n=== 3. Stream Source (getStreamSource) ===\n";
if (!empty($episodes[0]['server_data'][0])) {
    $firstEp = $episodes[0]['server_data'][0];
    $episodeData = $firstEp['link_embed'] ?? null;
    
    if ($episodeData) {
        echo "Testing with episodeData: " . $episodeData . "\n";
        
        // Try DU server (HLS)
        echo "\nDU Server (HLS):\n";
        $sourceDU = $adapter->getStreamSource($episodeData, 'ANIMEVIETSUB', 'DU');
        echo "  Server: " . ($sourceDU['server'] ?? 'N/A') . "\n";
        echo "  Type: " . ($sourceDU['type'] ?? 'N/A') . "\n";
        echo "  CORS Required: " . ($sourceDU['corsProxyRequired'] ? 'Yes' : 'No') . "\n";
        echo "  URL: " . substr($sourceDU['url'] ?? 'N/A', 0, 100) . (strlen($sourceDU['url'] ?? '') > 100 ? '...' : '') . "\n";
        
        // Try HDX server (EMBED)
        echo "\nHDX Server (EMBED):\n";
        $sourceHDX = $adapter->getStreamSource($episodeData, 'ANIMEVIETSUB', 'HDX');
        echo "  Server: " . ($sourceHDX['server'] ?? 'N/A') . "\n";
        echo "  Type: " . ($sourceHDX['type'] ?? 'N/A') . "\n";
        echo "  CORS Required: " . ($sourceHDX['corsProxyRequired'] ? 'Yes' : 'No') . "\n";
        echo "  URL: " . substr($sourceHDX['url'] ?? 'N/A', 0, 100) . (strlen($sourceHDX['url'] ?? '') > 100 ? '...' : '') . "\n";
    } else {
        echo "No episodeData available\n";
    }
}

// Test 4: Pagination (should return 0 for animapper)
echo "\n=== 4. Pagination (getPagination) ===\n";
$pagination = $adapter->getPagination();
echo "Total Pages: " . $pagination['total'] . "\n";
echo "Total Films: " . $pagination['films'] . "\n";
echo "Per Page: " . $pagination['perPage'] . "\n";
echo "Current Page: " . $pagination['currentPage'] . "\n";

echo "\n====== Test Complete ======\n";
