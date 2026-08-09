<?php

namespace App\Jobs;

use App\Models\CloudAsset;
use App\Services\CloudStorage\GoogleDriveStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use RuntimeException;
use Throwable;

class UploadCloudAsset implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 3600;
    public int $uniqueFor = 86400;

    public function __construct(public int $cloudAssetId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->cloudAssetId;
    }

    public function handle(GoogleDriveStorage $storage): void
    {
        $this->log('info', "Job started for CloudAsset ID: {$this->cloudAssetId}");

        $asset = CloudAsset::query()->find($this->cloudAssetId);

        if (! $asset) {
            $this->log('warning', "CloudAsset ID {$this->cloudAssetId} not found in database.");
            return;
        }

        if ($asset->status === CloudAsset::STATUS_SUCCESS && $asset->storage_url) {
            $this->log('info', "CloudAsset ID {$this->cloudAssetId} is already completed. Skipping.");
            return;
        }

        if ($asset->status === CloudAsset::STATUS_PROGRESS && $asset->updated_at && $asset->updated_at->gt(now()->subMinutes(60))) {
            $this->log('info', "CloudAsset ID {$this->cloudAssetId} is currently being processed by another worker (updated at {$asset->updated_at}). Skipping duplicate execution.");
            return;
        }

        $sourceUrl = $asset->asset_url;
        $asset->increment('attempts');
        $asset->update([
            'status' => CloudAsset::STATUS_PROGRESS,
            'last_error' => null,
        ]);

        $this->log('info', "Processing Asset ID {$this->cloudAssetId}: URL={$sourceUrl}, Resource={$asset->resource_type}-{$asset->resource_type_id}, Attempt={$asset->attempts}");

        $temporaryPath = tempnam(sys_get_temp_dir(), 'cloud-asset-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary file for cloud asset upload.');
        }

        try {
            $mimeType = $this->downloadSource($sourceUrl, $temporaryPath);

            $fileSize = is_file($temporaryPath) ? filesize($temporaryPath) : 0;
            $minSize = $asset->asset_type === CloudAsset::ASSET_VIDEO
                ? (int) config('services.cloud_assets.min_video_bytes', 512 * 1024)
                : (int) config('services.cloud_assets.min_image_bytes', 1024);

            if ($fileSize < $minSize) {
                throw new RuntimeException("Downloaded asset file size ({$fileSize} bytes) is smaller than required minimum ({$minSize} bytes). Download incomplete or invalid.");
            }

            $this->log('info', "Downloaded asset for ID {$this->cloudAssetId} successfully: Mime={$mimeType}, Size={$fileSize} bytes. Uploading to Google Drive...");

            $uploaded = $storage->upload(
                $temporaryPath,
                $this->fileName($asset, $mimeType),
                $mimeType,
                $this->folderId($asset)
            );

            $asset->refresh();

            if ($asset->asset_url !== $sourceUrl) {
                $this->log('warning', "Asset URL changed during upload for ID {$this->cloudAssetId}. Skipping state update.");
                return;
            }

            $asset->update([
                'status' => CloudAsset::STATUS_SUCCESS,
                'storage_file_id' => $uploaded['id'],
                'storage_url' => $uploaded['url'],
                'last_error' => null,
                'uploaded_at' => now(),
            ]);

            $this->log('info', "Successfully completed Asset ID {$this->cloudAssetId}: FileID={$uploaded['id']}, StorageURL={$uploaded['url']}");
        } catch (Throwable $exception) {
            $this->log('error', "Failed processing Asset ID {$this->cloudAssetId}: {$exception->getMessage()}", [
                'exception' => $exception->getMessage(),
                'trace' => Str::limit($exception->getTraceAsString(), 2000),
            ]);

            $asset->update([
                'status' => CloudAsset::STATUS_FAIL,
                'last_error' => Str::limit($exception->getMessage(), 65000, ''),
            ]);

            throw $exception;
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->log('error', "Job permanently failed for CloudAsset ID {$this->cloudAssetId}: {$exception->getMessage()}");

        CloudAsset::query()
            ->whereKey($this->cloudAssetId)
            ->update([
                'status' => CloudAsset::STATUS_FAIL,
                'last_error' => Str::limit($exception->getMessage(), 65000, ''),
            ]);
    }

    private function folderId(CloudAsset $asset): string
    {
        $folderKey = match ($asset->resource_type) {
            CloudAsset::RESOURCE_FILM_POSTER => 'posters',
            CloudAsset::RESOURCE_FILM_THUMBNAIL => 'thumbnails',
            CloudAsset::RESOURCE_FILM_TRAILER,
            CloudAsset::RESOURCE_EPISODE => 'video',
            default => throw new RuntimeException("Unsupported cloud asset resource type: {$asset->resource_type}"),
        };

        $folderId = config("services.google_drive.folders.{$folderKey}");

        if (! $folderId) {
            throw new RuntimeException("Google Drive folder is not configured: {$folderKey}");
        }

        return $folderId;
    }

    private function downloadSource(string $sourceUrl, string $targetPath): string
    {
        [$mediaUrl, $referer] = $this->resolveMediaUrlAndReferer($sourceUrl);

        if ($mediaUrl !== $sourceUrl) {
            $this->log('info', "Resolved player wrapper URL for Asset ID {$this->cloudAssetId}: MediaURL={$mediaUrl}, Referer=" . ($referer ?? 'none'));
        }

        if (preg_match('/(^|\\.)((youtube\\.com)|(youtu\\.be))($|\\/)/i', parse_url($mediaUrl, PHP_URL_HOST) ?? '')) {
            throw new RuntimeException('YouTube assets require a media downloader and are not direct-download URLs.');
        }

        if ($localPath = $this->localPath($mediaUrl)) {
            $this->log('info', "Copying local asset file for Asset ID {$this->cloudAssetId}: {$localPath}");
            if (! copy($localPath, $targetPath)) {
                throw new RuntimeException("Unable to copy local asset: {$localPath}");
            }

            return $this->mimeType($targetPath, $mediaUrl);
        }

        if (! filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException("Asset URL is not downloadable: {$mediaUrl}");
        }

        if ($this->isM3u8Url($mediaUrl)) {
            $this->log('info', "Downloading m3u8 stream with FFmpeg for Asset ID {$this->cloudAssetId}: {$mediaUrl}");
            return $this->downloadM3u8($mediaUrl, $targetPath, $referer);
        }

        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ];
        if ($referer) {
            $headers['Referer'] = $referer;
        }

        $this->log('info', "Downloading direct file via HTTP for Asset ID {$this->cloudAssetId}: {$mediaUrl}");

        $response = Http::timeout(300)
            ->retry(2, 1000)
            ->withHeaders($headers)
            ->sink($targetPath)
            ->get($mediaUrl);

        if (! $response->successful()) {
            throw new RuntimeException("Unable to download asset ({$response->status()}): {$mediaUrl}");
        }

        $mimeType = $this->mimeType($targetPath, $mediaUrl, $response);

        if ($this->isM3u8MimeType($mimeType) || $this->isM3u8Content($targetPath)) {
            $this->log('info', "Detected M3U8 content after HTTP fetch. Downloading stream with FFmpeg for Asset ID {$this->cloudAssetId}");
            return $this->downloadM3u8($mediaUrl, $targetPath, $referer);
        }

        if ($this->isHtmlContent($mimeType, $targetPath)) {
            $this->log('info', "Detected HTML player page content for Asset ID {$this->cloudAssetId}. Attempting to extract embedded m3u8 URL...");
            $extractedM3u8 = $this->extractM3u8FromHtml($targetPath);
            if ($extractedM3u8) {
                $this->log('info', "Extracted m3u8 URL from HTML for Asset ID {$this->cloudAssetId}: {$extractedM3u8}");
                if (is_file($targetPath)) {
                    unlink($targetPath);
                }
                return $this->downloadM3u8($extractedM3u8, $targetPath, $referer ?: $mediaUrl);
            }
        }

        return $mimeType;
    }

    private function resolveMediaUrlAndReferer(string $sourceUrl, int $depth = 0): array
    {
        if ($depth > 3) {
            return [$sourceUrl, null];
        }

        $sourceUrl = htmlspecialchars_decode($sourceUrl, ENT_QUOTES | ENT_HTML5);
        $parsed = parse_url($sourceUrl);
        $queryString = $parsed['query'] ?? '';

        if ($queryString !== '') {
            parse_str($queryString, $queryParams);

            $targetKeys = ['url', 'link', 'file', 'src', 'video', 'stream', 'm3u8', 'source', 'target', 'href'];

            foreach ($targetKeys as $key) {
                if (isset($queryParams[$key]) && is_string($queryParams[$key])) {
                    $candidate = trim($queryParams[$key]);
                    if (filter_var($candidate, FILTER_VALIDATE_URL) && $candidate !== $sourceUrl) {
                        [$resolvedUrl, $nestedReferer] = $this->resolveMediaUrlAndReferer($candidate, $depth + 1);
                        return [$resolvedUrl, $nestedReferer ?: $sourceUrl];
                    }
                }
            }

            foreach ($queryParams as $val) {
                if (is_string($val)) {
                    $candidate = trim($val);
                    if ((str_starts_with($candidate, 'http://') || str_starts_with($candidate, 'https://')) && filter_var($candidate, FILTER_VALIDATE_URL) && $candidate !== $sourceUrl) {
                        [$resolvedUrl, $nestedReferer] = $this->resolveMediaUrlAndReferer($candidate, $depth + 1);
                        return [$resolvedUrl, $nestedReferer ?: $sourceUrl];
                    }
                }
            }
        }

        return [$sourceUrl, null];
    }

    private function isM3u8Url(string $sourceUrl): bool
    {
        [$mediaUrl] = $this->resolveMediaUrlAndReferer($sourceUrl);

        $path = parse_url($mediaUrl, PHP_URL_PATH) ?? '';
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'm3u8') {
            return true;
        }

        return str_contains(strtolower($mediaUrl), '.m3u8');
    }

    private function isM3u8MimeType(string $mimeType): bool
    {
        return in_array(
            strtolower($mimeType),
            ['application/x-mpegurl', 'application/vnd.apple.mpegurl', 'audio/mpegurl', 'audio/x-mpegurl'],
            true
        );
    }

    private function isM3u8Content(string $targetPath): bool
    {
        if (! is_file($targetPath) || filesize($targetPath) === 0 || filesize($targetPath) > 5 * 1024 * 1024) {
            return false;
        }

        $handle = fopen($targetPath, 'rb');
        if (! $handle) {
            return false;
        }

        $header = fread($handle, 512) ?: '';
        fclose($handle);

        return str_starts_with(trim($header), '#EXTM3U') || str_contains($header, '#EXT-X-STREAM-INF');
    }

    private function isHtmlContent(string $mimeType, string $targetPath): bool
    {
        if (str_contains(strtolower($mimeType), 'text/html') || str_contains(strtolower($mimeType), 'application/xhtml+xml')) {
            return true;
        }

        if (! is_file($targetPath) || filesize($targetPath) === 0 || filesize($targetPath) > 5 * 1024 * 1024) {
            return false;
        }

        $handle = fopen($targetPath, 'rb');
        if (! $handle) {
            return false;
        }

        $header = fread($handle, 512) ?: '';
        fclose($handle);

        $trimmed = strtolower(trim($header));
        return str_starts_with($trimmed, '<!doctype html') || str_starts_with($trimmed, '<html') || str_contains($trimmed, '<head');
    }

    private function extractM3u8FromHtml(string $targetPath): ?string
    {
        if (! is_file($targetPath) || filesize($targetPath) === 0 || filesize($targetPath) > 5 * 1024 * 1024) {
            return null;
        }

        $content = file_get_contents($targetPath);
        if ($content === false) {
            return null;
        }

        if (preg_match('/https?:\/\/[^\s"\'<>\\\\]+\.m3u8[^\s"\'<>\\\\]*/i', $content, $matches)) {
            return $matches[0];
        }

        return null;
    }

    private function downloadM3u8(string $sourceUrl, string $targetPath, ?string $referer = null): string
    {
        $ffmpegBin = '/usr/bin/ffmpeg';

        if (! is_executable($ffmpegBin)) {
            $ffmpegBin = 'ffmpeg';
        }

        $headerStr = "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n";
        if ($referer) {
            $headerStr .= "Referer: {$referer}\r\n";
        }

        $process = new Process([
            $ffmpegBin,
            '-y',
            '-hide_banner',
            '-loglevel', 'error',
            '-headers', $headerStr,
            '-user_agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            '-i', $sourceUrl,
            '-c', 'copy',
            '-bsf:a', 'aac_adtstoasc',
            '-f', 'mp4',
            $targetPath,
        ]);
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput());
            $this->log('warning', "Primary FFmpeg process failed for Asset ID {$this->cloudAssetId}: {$errorOutput}. Trying fallback re-encode...");

            $fallbackProcess = new Process([
                $ffmpegBin,
                '-y',
                '-hide_banner',
                '-loglevel', 'error',
                '-headers', $headerStr,
                '-user_agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                '-i', $sourceUrl,
                '-c:v', 'libx264',
                '-c:a', 'aac',
                '-preset', 'fast',
                '-f', 'mp4',
                $targetPath,
            ]);
            $fallbackProcess->setTimeout(3600);
            $fallbackProcess->run();

            if (! $fallbackProcess->isSuccessful()) {
                $errorMsg = trim($fallbackProcess->getErrorOutput() ?: $errorOutput);
                $this->log('error', "FFmpeg fallback failed for Asset ID {$this->cloudAssetId}: {$errorMsg}");
                throw new RuntimeException("FFmpeg failed to download m3u8 stream: {$errorMsg}");
            }
        }

        return 'video/mp4';
    }

    private function localPath(string $sourceUrl): ?string
    {
        $sourcePath = parse_url($sourceUrl, PHP_URL_PATH);

        if (! is_string($sourcePath) || $sourcePath === '') {
            return null;
        }

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $sourceHost = parse_url($sourceUrl, PHP_URL_HOST);

        if ($sourceHost && $appHost && $sourceHost !== $appHost) {
            return null;
        }

        $relativePath = ltrim($sourcePath, '/');
        $relativePath = preg_replace('#^public/#', '', $relativePath);
        $candidate = realpath(public_path($relativePath));
        $publicRoot = realpath(public_path());

        if (! $candidate || ! $publicRoot || ! str_starts_with($candidate, $publicRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return is_file($candidate) ? $candidate : null;
    }

    private function mimeType(string $path, string $sourceUrl, ?Response $response = null): string
    {
        $mimeType = $response?->header('Content-Type');

        if (! $mimeType || $mimeType === 'application/octet-stream') {
            $mimeType = mime_content_type($path) ?: null;
        }

        if (! $mimeType) {
            $extension = strtolower(pathinfo(parse_url($sourceUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
            $mimeType = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'mp4' => 'video/mp4',
                'webm' => 'video/webm',
                'mkv' => 'video/x-matroska',
                default => 'application/octet-stream',
            };
        }

        return trim(explode(';', $mimeType)[0]);
    }

    private function fileName(CloudAsset $asset, string $mimeType): string
    {
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            default => pathinfo(parse_url($asset->asset_url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'bin',
        };

        return "{$asset->resource_type}-{$asset->resource_type_id}.{$extension}";
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::log($level, "[UploadCloudAsset] {$message}", $context);

        try {
            $logFile = storage_path('logs/cloud_assets-' . date('Y-m-d') . '.log');
            $date = date('Y-m-d H:i:s');
            $contextStr = $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';
            file_put_contents($logFile, "[{$date}] " . strtoupper($level) . ": [UploadCloudAsset] {$message}{$contextStr}\n", FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
            // Ignore file logging errors
        }
    }
}
