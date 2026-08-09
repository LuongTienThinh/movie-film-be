<?php

namespace Tests\Feature;

use App\Models\CloudAsset;
use App\Models\Film;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncCloudAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_cloud_assets_can_retry_failed_assets(): void
    {
        $typeId = \Illuminate\Support\Facades\DB::table('types')->insertGetId([
            'name' => 'Single',
            'slug' => 'single',
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $statusId = \Illuminate\Support\Facades\DB::table('statuses')->insertGetId([
            'name' => 'Completed',
            'slug' => 'completed',
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $filmId = \Illuminate\Support\Facades\DB::table('films')->insertGetId([
            'name' => 'Test Film',
            'origin_name' => 'Test Film Origin',
            'description' => 'Test Description',
            'slug' => 'test-film',
            'server' => 'kkphim',
            'quality' => 'HD',
            'trailer_url' => '',
            'poster_url' => 'http://localhost/public/uploads/posters/test.webp',
            'thumbnail_url' => 'http://localhost/public/uploads/thumbnails/test.webp',
            'episode_current' => 1,
            'episode_total' => 12,
            'year' => 2024,
            'type_id' => $typeId,
            'status_id' => $statusId,
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $asset = CloudAsset::create([
            'resource_type' => CloudAsset::RESOURCE_FILM_POSTER,
            'resource_type_id' => $filmId,
            'asset_type' => CloudAsset::ASSET_IMAGE,
            'asset_url' => 'http://localhost/public/uploads/posters/test.webp',
            'status' => CloudAsset::STATUS_FAIL,
            'last_error' => 'Previous error',
        ]);

        $this->artisan('cloud-assets:sync --retry-failed')
            ->assertExitCode(0);

        $asset->refresh();
        $this->assertEquals(CloudAsset::STATUS_PENDING, $asset->status);
        $this->assertNull($asset->last_error);
    }

    public function test_prioritizes_cloud_asset_storage_url_over_original_url_in_api(): void
    {
        $typeId = \Illuminate\Support\Facades\DB::table('types')->insertGetId([
            'name' => 'Single',
            'slug' => 'single-test',
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $statusId = \Illuminate\Support\Facades\DB::table('statuses')->insertGetId([
            'name' => 'Completed',
            'slug' => 'completed-test',
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $film = Film::create([
            'name' => 'Test Film Storage URL',
            'origin_name' => 'Test Origin',
            'description' => 'Test Desc',
            'slug' => 'test-film-storage-url',
            'server' => 'kkphim',
            'quality' => 'HD',
            'trailer_url' => 'https://example.com/trailer.mp4',
            'poster_url' => 'https://example.com/original-poster.jpg',
            'thumbnail_url' => 'https://example.com/original-thumb.jpg',
            'episode_current' => 1,
            'episode_total' => 1,
            'year' => 2026,
            'type_id' => $typeId,
            'status_id' => $statusId,
        ]);

        CloudAsset::create([
            'resource_type' => CloudAsset::RESOURCE_FILM_POSTER,
            'resource_type_id' => $film->id,
            'asset_type' => CloudAsset::ASSET_IMAGE,
            'asset_url' => 'https://example.com/original-poster.jpg',
            'storage_url' => 'https://drive.google.com/file/d/poster-123/view',
            'status' => CloudAsset::STATUS_SUCCESS,
        ]);

        $trait = new class {
            use \App\Traits\FilmTrait;
        };

        $formatted = $trait->formatFilm($film->fresh());

        $this->assertEquals('https://drive.google.com/file/d/poster-123/view', $formatted['poster_url']);
        $this->assertEquals('https://example.com/original-thumb.jpg', $formatted['thumbnail_url']);
    }
}
