<?php

namespace Tests\Unit;

use App\Jobs\UploadCloudAsset;
use App\Models\CloudAsset;
use App\Services\CloudStorage\GoogleDriveStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

class UploadCloudAssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_fails_job_when_downloaded_video_file_size_is_too_small(): void
    {
        Http::fake([
            'https://example.com/small-video.mp4' => Http::response('tiny content', 200, ['Content-Type' => 'video/mp4']),
        ]);

        $asset = CloudAsset::create([
            'resource_type' => CloudAsset::RESOURCE_EPISODE,
            'resource_type_id' => 1,
            'asset_type' => CloudAsset::ASSET_VIDEO,
            'asset_url' => 'https://example.com/small-video.mp4',
            'status' => CloudAsset::STATUS_PENDING,
        ]);

        $mockStorage = $this->createMock(GoogleDriveStorage::class);
        $mockStorage->expects($this->never())->method('upload');

        $job = new UploadCloudAsset($asset->id);

        try {
            $job->handle($mockStorage);
            $this->fail('Expected RuntimeException due to small file size was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('smaller than required minimum', $e->getMessage());
        }

        $asset->refresh();
        $this->assertEquals(CloudAsset::STATUS_FAIL, $asset->status);
        $this->assertStringContainsString('smaller than required minimum', $asset->last_error);
    }

    public function test_detects_m3u8_urls(): void
    {
        $job = new UploadCloudAsset(1);
        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('isM3u8Url');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($job, 'https://example.com/stream/index.m3u8'));
        $this->assertTrue($method->invoke($job, 'https://example.com/stream.m3u8?token=xyz'));
        $this->assertFalse($method->invoke($job, 'https://example.com/video.mp4'));
    }

    public function test_detects_m3u8_mime_type(): void
    {
        $job = new UploadCloudAsset(1);
        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('isM3u8MimeType');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($job, 'application/x-mpegURL'));
        $this->assertTrue($method->invoke($job, 'application/vnd.apple.mpegurl'));
        $this->assertFalse($method->invoke($job, 'video/mp4'));
    }

    public function test_resolves_player_wrapper_url_and_referer(): void
    {
        $job = new UploadCloudAsset(1);
        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('resolveMediaUrlAndReferer');
        $method->setAccessible(true);

        $playerUrl = 'https://player.phimapi.com/player/?url=https://s6.kkphimplayer6.com/20260306/1jnKlixh/index.m3u8';
        [$resolvedUrl, $referer] = $method->invoke($job, $playerUrl);

        $this->assertEquals('https://s6.kkphimplayer6.com/20260306/1jnKlixh/index.m3u8', $resolvedUrl);
        $this->assertEquals($playerUrl, $referer);
    }

    public function test_extracts_m3u8_from_html_content(): void
    {
        $job = new UploadCloudAsset(1);
        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('extractM3u8FromHtml');
        $method->setAccessible(true);

        $tempFile = tempnam(sys_get_temp_dir(), 'test-html-');
        file_put_contents($tempFile, '<html><body><script>const file = "https://cdn.example.com/stream/index.m3u8";</script></body></html>');

        try {
            $extracted = $method->invoke($job, $tempFile);
            $this->assertEquals('https://cdn.example.com/stream/index.m3u8', $extracted);
        } finally {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
