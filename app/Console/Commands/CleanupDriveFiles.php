<?php

namespace App\Console\Commands;

use App\Models\CloudAsset;
use App\Services\CloudStorage\GoogleDriveStorage;
use Illuminate\Console\Command;
use Throwable;

class CleanupDriveFiles extends Command
{
    protected $signature = 'cloud-assets:cleanup-drive-files
                            {--min-video-size=1048576 : Minimum video file size in bytes (default: 1MB = 1048576 bytes)}
                            {--min-image-size=1024 : Minimum image file size in bytes (default: 1KB = 1024 bytes)}
                            {--skip-duplicates : Skip deleting duplicate files with the same name}';

    protected $description = 'Clean up small/corrupted files and duplicate filename files on Google Drive';

    public function handle(GoogleDriveStorage $storage): int
    {
        $minVideoSize = (int) $this->option('min-video-size');
        $minImageSize = (int) $this->option('min-image-size');
        $skipDuplicates = (bool) $this->option('skip-duplicates');

        $this->info("Scanning Google Drive for cleanup (Small files & duplicate filenames)...");

        try {
            $drive = $storage->getDrive();
        } catch (Throwable $e) {
            $this->error("Failed to connect to Google Drive: " . $e->getMessage());
            return self::FAILURE;
        }

        $folders = [
            'video' => [
                'id' => config('services.google_drive.folders.video'),
                'min_size' => $minVideoSize,
                'type' => 'video',
            ],
            'posters' => [
                'id' => config('services.google_drive.folders.posters'),
                'min_size' => $minImageSize,
                'type' => 'image',
            ],
            'thumbnails' => [
                'id' => config('services.google_drive.folders.thumbnails'),
                'min_size' => $minImageSize,
                'type' => 'image',
            ],
        ];

        $totalSmallDeleted = 0;
        $totalDuplicatesDeleted = 0;

        foreach ($folders as $folderKey => $info) {
            $folderId = $info['id'];
            $minSize = $info['min_size'];
            $type = $info['type'];

            if (! $folderId) {
                $this->warn("Skipping '{$folderKey}' folder: Google Drive folder ID not configured.");
                continue;
            }

            $this->info("Scanning folder '{$folderKey}' (ID: {$folderId})...");

            $allFiles = [];
            $pageToken = null;

            do {
                try {
                    $response = $drive->files->listFiles([
                        'q' => "'{$folderId}' in parents and trashed = false",
                        'fields' => 'nextPageToken, files(id, name, mimeType, size, createdTime, modifiedTime)',
                        'pageSize' => 1000,
                        'pageToken' => $pageToken,
                        'supportsAllDrives' => true,
                        'includeItemsFromAllDrives' => true,
                    ]);
                } catch (Throwable $e) {
                    $this->error("Error listing files in folder '{$folderKey}': " . $e->getMessage());
                    break;
                }

                $files = $response->getFiles();
                foreach ($files as $file) {
                    $allFiles[] = $file;
                }

                $pageToken = $response->getNextPageToken();
            } while ($pageToken);

            $validFilesByName = [];
            $deletedSmallInFolder = 0;
            $deletedDupesInFolder = 0;

            foreach ($allFiles as $file) {
                $fileSize = (int) $file->getSize();
                $fileId = $file->getId();
                $fileName = $file->getName();

                if ($fileSize < $minSize) {
                    $this->line("Deleting small {$type} file '{$fileName}' (ID: {$fileId}, Size: {$fileSize} bytes < {$minSize} bytes)...");

                    try {
                        $drive->files->delete($fileId, ['supportsAllDrives' => true]);
                        $deletedSmallInFolder++;
                        $totalSmallDeleted++;

                        CloudAsset::query()
                            ->where('storage_file_id', $fileId)
                            ->update([
                                'status' => CloudAsset::STATUS_FAIL,
                                'storage_file_id' => null,
                                'storage_url' => null,
                                'last_error' => "Deleted small file from Google Drive ({$fileSize} bytes < {$minSize} bytes)",
                            ]);
                    } catch (Throwable $e) {
                        $this->error("Failed to delete file ID {$fileId}: " . $e->getMessage());
                    }
                } else {
                    $validFilesByName[$fileName][] = $file;
                }
            }

            if (! $skipDuplicates) {
                foreach ($validFilesByName as $fileName => $fileGroup) {
                    if (count($fileGroup) <= 1) {
                        continue;
                    }

                    $this->line("Found " . count($fileGroup) . " duplicate files named '{$fileName}'. Resolving duplicate...");

                    $fileIds = array_map(fn ($f) => $f->getId(), $fileGroup);

                    $activeAsset = CloudAsset::query()
                        ->whereIn('storage_file_id', $fileIds)
                        ->where('status', CloudAsset::STATUS_SUCCESS)
                        ->first();

                    $keepFileId = $activeAsset?->storage_file_id;

                    if (! $keepFileId) {
                        usort($fileGroup, function ($a, $b) {
                            $sizeDiff = (int) $b->getSize() <=> (int) $a->getSize();
                            if ($sizeDiff !== 0) {
                                return $sizeDiff;
                            }
                            return strtotime($b->getModifiedTime() ?: $b->getCreatedTime() ?: 'now')
                               <=> strtotime($a->getModifiedTime() ?: $a->getCreatedTime() ?: 'now');
                        });
                        $keepFileId = $fileGroup[0]->getId();
                    }

                    foreach ($fileGroup as $file) {
                        $fileId = $file->getId();
                        if ($fileId === $keepFileId) {
                            continue;
                        }

                        $this->line("Deleting duplicate file '{$fileName}' (ID: {$fileId})...");

                        try {
                            $drive->files->delete($fileId, ['supportsAllDrives' => true]);
                            $deletedDupesInFolder++;
                            $totalDuplicatesDeleted++;

                            CloudAsset::query()
                                ->where('storage_file_id', $fileId)
                                ->where('storage_file_id', '!=', $keepFileId)
                                ->update([
                                    'status' => CloudAsset::STATUS_FAIL,
                                    'storage_file_id' => null,
                                    'storage_url' => null,
                                    'last_error' => "Deleted duplicate file from Google Drive",
                                ]);
                        } catch (Throwable $e) {
                            $this->error("Failed to delete duplicate file ID {$fileId}: " . $e->getMessage());
                        }
                    }
                }
            }

            $this->info("Finished '{$folderKey}': Deleted {$deletedSmallInFolder} small file(s), {$deletedDupesInFolder} duplicate file(s).");
        }

        $summary = "Drive cleanup completed. Deleted small files: {$totalSmallDeleted}, Deleted duplicate files: {$totalDuplicatesDeleted}";
        $this->info($summary);
        $this->log('info', $summary);

        return self::SUCCESS;
    }

    private function log(string $level, string $message, array $context = []): void
    {
        try {
            $logFile = 'cleanup_drive-' . date('Y-m-d') . '.log';
            \Illuminate\Support\Facades\Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/' . $logFile),
            ])->log($level, "[CleanupDriveFiles] {$message}", $context);
        } catch (\Throwable) {
            \Illuminate\Support\Facades\Log::log($level, "[CleanupDriveFiles] {$message}", $context);
        }
    }
}
