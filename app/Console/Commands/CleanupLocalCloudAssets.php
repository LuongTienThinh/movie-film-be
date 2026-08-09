<?php

namespace App\Console\Commands;

use App\Models\CloudAsset;
use App\Services\CloudStorage\GoogleDriveStorage;
use Illuminate\Console\Command;

class CleanupLocalCloudAssets extends Command
{
    protected $signature = 'cloud-assets:cleanup-local-files {--chunk=500 : Batch size for database query}';

    protected $description = 'Remove local storage files for assets successfully uploaded to cloud storage';

    public function handle(GoogleDriveStorage $storage): int
    {
        $this->info("Scanning database for cloud_assets with status = 'success' to clean up local storage files...");

        $chunkSize = max(1, (int) $this->option('chunk'));
        $totalDeleted = 0;
        $totalBytesFreed = 0;

        CloudAsset::query()
            ->where('status', CloudAsset::STATUS_SUCCESS)
            ->whereNotNull('storage_url')
            ->where('storage_url', '!=', '')
            ->chunkById($chunkSize, function ($assets) use ($storage, &$totalDeleted, &$totalBytesFreed) {
                foreach ($assets as $asset) {
                    $candidates = $this->getLocalFileCandidates($asset);

                    foreach ($candidates as $path) {
                        if (! is_file($path)) {
                            continue;
                        }

                        $localSize = filesize($path) ?: 0;
                        $shouldDelete = true;

                        if ($asset->storage_file_id) {
                            $driveSize = $this->getDriveFileSize($storage, $asset->storage_file_id);

                            if ($driveSize !== null && $driveSize !== $localSize) {
                                $this->line("Filesize mismatch for Asset ID {$asset->id} (Local: {$localSize} bytes vs Drive: {$driveSize} bytes). Re-uploading to Google Drive...");
                                $uploaded = $this->reuploadAsset($storage, $asset, $path);

                                if ($uploaded) {
                                    $asset->update([
                                        'storage_file_id' => $uploaded['id'],
                                        'storage_url' => $uploaded['url'],
                                        'uploaded_at' => now(),
                                    ]);
                                    $this->line("Re-uploaded Asset ID {$asset->id} successfully.");
                                } else {
                                    $this->error("Failed to re-upload Asset ID {$asset->id}. Keeping local file.");
                                    $shouldDelete = false;
                                }
                            }
                        }

                        if ($shouldDelete && @unlink($path)) {
                            $totalDeleted++;
                            $totalBytesFreed += $localSize;
                            $this->line("Deleted local file: {$path} (" . round($localSize / 1024, 2) . " KB)");
                        }
                    }
                }
            });

        $needCompressResult = $this->cleanupNeedCompressFolders();
        $totalDeleted += $needCompressResult['corrupted'] + $needCompressResult['duplicates'];
        $totalBytesFreed += $needCompressResult['bytes_freed'];

        $freedMb = round($totalBytesFreed / (1024 * 1024), 2);
        $summary = "Local cleanup completed. Removed {$totalDeleted} local file(s) (including {$needCompressResult['corrupted']} corrupted & {$needCompressResult['duplicates']} duplicate files in need-compress), freed {$freedMb} MB of disk space.";
        $this->info($summary);
        $this->log('info', $summary);

        return self::SUCCESS;
    }

    private function log(string $level, string $message, array $context = []): void
    {
        try {
            $logFile = 'cleanup_local-' . date('Y-m-d') . '.log';
            \Illuminate\Support\Facades\Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/' . $logFile),
            ])->log($level, "[CleanupLocalCloudAssets] {$message}", $context);
        } catch (\Throwable) {
            \Illuminate\Support\Facades\Log::log($level, "[CleanupLocalCloudAssets] {$message}", $context);
        }
    }

    private function getLocalFileCandidates(CloudAsset $asset): array
    {
        $paths = [];

        $resolved = $this->resolveLocalPath($asset->asset_url);
        if ($resolved) {
            $paths[] = $resolved;
        }

        $filename = pathinfo(parse_url($asset->asset_url, PHP_URL_PATH) ?? '', PATHINFO_FILENAME);

        if ($filename !== '') {
            if ($asset->resource_type === CloudAsset::RESOURCE_FILM_POSTER) {
                $paths[] = public_path("uploads/posters/{$filename}.webp");
            } elseif ($asset->resource_type === CloudAsset::RESOURCE_FILM_THUMBNAIL) {
                $paths[] = public_path("uploads/thumbnails/{$filename}.webp");
            }
        }

        return array_unique(array_filter($paths));
    }

    private function resolveLocalPath(string $sourceUrl): ?string
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

    private function cleanupNeedCompressFolders(): array
    {
        $deletedCorrupted = 0;
        $deletedDuplicates = 0;
        $bytesFreed = 0;

        $folders = [
            public_path('uploads/posters-need-compress'),
            public_path('uploads/thumbnails-need-compress'),
        ];

        foreach ($folders as $folder) {
            if (! is_dir($folder)) {
                continue;
            }

            $files = glob($folder . '/*');
            if (empty($files)) {
                continue;
            }

            $seenBasenames = [];

            foreach ($files as $file) {
                if (! is_file($file)) {
                    continue;
                }

                $size = filesize($file) ?: 0;
                $filename = basename($file);

                // 1. Remove corrupted / small invalid files (< 1KB or non-image)
                if ($size < 1024 || @getimagesize($file) === false) {
                    if (@unlink($file)) {
                        $deletedCorrupted++;
                        $bytesFreed += $size;
                        $this->line("Deleted corrupted/small file in need-compress: {$file} ({$size} bytes)");
                    }
                    continue;
                }

                // 2. Remove duplicate files within the need-compress folder
                if (isset($seenBasenames[$filename])) {
                    if (@unlink($file)) {
                        $deletedDuplicates++;
                        $bytesFreed += $size;
                        $this->line("Deleted duplicate file in need-compress: {$file}");
                    }
                } else {
                    $seenBasenames[$filename] = true;
                }
            }
        }

        return [
            'corrupted' => $deletedCorrupted,
            'duplicates' => $deletedDuplicates,
            'bytes_freed' => $bytesFreed,
        ];
    }

    private function getDriveFileSize(GoogleDriveStorage $storage, string $fileId): ?int
    {
        try {
            $drive = $storage->getDrive();
            $file = $drive->files->get($fileId, [
                'fields' => 'id, size',
                'supportsAllDrives' => true,
            ]);

            return isset($file->size) ? (int) $file->size : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function reuploadAsset(GoogleDriveStorage $storage, CloudAsset $asset, string $localPath): ?array
    {
        try {
            $mimeType = @mime_content_type($localPath) ?: 'image/webp';
            $folderKey = match ($asset->resource_type) {
                CloudAsset::RESOURCE_FILM_POSTER => 'posters',
                CloudAsset::RESOURCE_FILM_THUMBNAIL => 'thumbnails',
                CloudAsset::RESOURCE_FILM_TRAILER,
                CloudAsset::RESOURCE_EPISODE => 'video',
                default => 'posters',
            };

            $folderId = config("services.google_drive.folders.{$folderKey}");
            if (! $folderId) {
                return null;
            }

            $fileName = basename($localPath);

            return $storage->upload($localPath, $fileName, $mimeType, $folderId);
        } catch (\Throwable $e) {
            $this->error("Re-upload error for asset {$asset->id}: " . $e->getMessage());
            return null;
        }
    }
}
