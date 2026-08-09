<?php

namespace App\Services\CloudStorage;

use Google\Client;
use Google\Http\MediaFileUpload;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Illuminate\Support\Facades\File;
use RuntimeException;

class GoogleDriveStorage
{
    public function upload(string $localPath, string $fileName, string $mimeType, string $folderId): array
    {
        if (! $folderId) {
            throw new RuntimeException('Google Drive destination folder is not configured.');
        }

        if ($existing = $this->findExistingFileInFolder($fileName, $mimeType, $folderId)) {
            return $existing;
        }

        if (! is_file($localPath)) {
            throw new RuntimeException("Asset file does not exist: {$localPath}");
        }

        $client = $this->client();
        $drive = new Drive($client);
        $metadata = new DriveFile([
            'name' => $fileName,
            'parents' => [$folderId],
        ]);

        $client->setDefer(true);

        try {
            $request = $drive->files->create($metadata, [
                'fields' => 'id,name,webViewLink,webContentLink',
                'supportsAllDrives' => true,
            ]);
            $handle = fopen($localPath, 'rb');
            $chunkSize = max(256 * 1024, (int) config('services.google_drive.chunk_size', 10 * 1024 * 1024));
            $media = new MediaFileUpload(
                $client,
                $request,
                $mimeType,
                '',
                true,
                $chunkSize
            );
            $media->setFileSize(filesize($localPath));

            $uploadedFile = false;
            while ($uploadedFile === false) {
                $chunk = fread($handle, $chunkSize);

                if ($chunk === false || ($chunk === '' && $media->getProgress() < filesize($localPath))) {
                    throw new RuntimeException('Unable to read the local asset during upload.');
                }

                $uploadedFile = $media->nextChunk($chunk);
            }
        } finally {
            $client->setDefer(false);
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }
        }

        $fileId = $uploadedFile->getId();

        if (config('services.google_drive.public', false)) {
            $permission = new Permission([
                'type' => 'anyone',
                'role' => 'reader',
            ]);
            $drive->permissions->create($fileId, $permission, [
                'supportsAllDrives' => true,
            ]);
        }

        $isImage = str_starts_with(strtolower($mimeType), 'image/');

        if ($isImage) {
            $storageUrl = "https://lh3.googleusercontent.com/d/{$fileId}";
        } else {
            $storageUrl = "https://drive.google.com/file/d/{$fileId}/preview";
        }

        return [
            'id' => $fileId,
            'url' => $storageUrl,
        ];
    }

    public function getDrive(): Drive
    {
        return new Drive($this->client());
    }

    public function deleteFile(string $fileId): bool
    {
        try {
            $drive = $this->getDrive();
            $drive->files->delete($fileId, ['supportsAllDrives' => true]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function client(): Client
    {

        $client = new Client();
        $client->setApplicationName(config('app.name'));
        $client->setClientId(config('services.google_drive.client_id'));
        $client->setClientSecret(config('services.google_drive.client_secret'));
        $client->setAccessType('offline');
        $client->setScopes([Drive::DRIVE_FILE]);

        $tokenPath = config('services.google_drive.token_path');

        if (! $tokenPath || ! is_file($tokenPath)) {
            throw new RuntimeException('Google Drive OAuth token file does not exist. Open the Drive OAuth authorization URL first.');
        }

        $token = json_decode(File::get($tokenPath), true);

        if (! is_array($token) || empty($token['refresh_token'])) {
            throw new RuntimeException('Google Drive OAuth token file does not contain a refresh token.');
        }
        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            $refreshedToken = $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);

            if (isset($refreshedToken['error'])) {
                throw new RuntimeException($refreshedToken['error_description'] ?? $refreshedToken['error']);
            }

            $refreshedToken['refresh_token'] = $token['refresh_token'];
            File::put($tokenPath, json_encode($refreshedToken, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            @chmod($tokenPath, 0600);
            $client->setAccessToken($refreshedToken);
        }

        return $client;
    }

    public function findExistingFileInFolder(string $fileName, string $mimeType, string $folderId): ?array
    {
        try {
            $drive = $this->getDrive();
            $response = $drive->files->listFiles([
                'q' => "'{$folderId}' in parents and name = '{$fileName}' and trashed = false",
                'fields' => 'files(id, name, mimeType, size)',
                'pageSize' => 1,
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ]);

            $files = $response->getFiles();

            if (! empty($files)) {
                $file = $files[0];
                $fileId = $file->getId();

                if (config('services.google_drive.public', false)) {
                    try {
                        $permission = new Permission([
                            'type' => 'anyone',
                            'role' => 'reader',
                        ]);
                        $drive->permissions->create($fileId, $permission, [
                            'supportsAllDrives' => true,
                        ]);
                    } catch (\Throwable) {
                        // Ignore permission errors if already existing
                    }
                }

                $isImage = str_starts_with(strtolower($mimeType), 'image/');
                $storageUrl = $isImage
                    ? "https://lh3.googleusercontent.com/d/{$fileId}"
                    : "https://drive.google.com/file/d/{$fileId}/preview";

                return [
                    'id' => $fileId,
                    'url' => $storageUrl,
                ];
            }
        } catch (\Throwable) {
            // Ignore search failure
        }

        return null;
    }
}
