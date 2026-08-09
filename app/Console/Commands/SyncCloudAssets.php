<?php

namespace App\Console\Commands;

use App\Jobs\UploadCloudAsset;
use App\Models\CloudAsset;
use App\Models\Episode;
use App\Models\Film;
use Illuminate\Console\Command;

class SyncCloudAssets extends Command
{
    protected $signature = 'cloud-assets:sync {--chunk=500 : Number of records to process per batch} {--retry-failed : Re-queue assets with failed status} {--force : Re-queue all assets}';

    protected $description = 'Register film and episode assets that need to be uploaded to cloud storage';

    private int $created = 0;
    private int $updated = 0;
    private int $queued = 0;

    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));

        Film::query()
            ->select(['id', 'thumbnail_url', 'poster_url', 'trailer_url'])
            ->chunkById($chunkSize, function ($films): void {
                foreach ($films as $film) {
                    $this->registerAsset(CloudAsset::RESOURCE_FILM_THUMBNAIL, $film->id, CloudAsset::ASSET_IMAGE, $film->thumbnail_url);
                    $this->registerAsset(CloudAsset::RESOURCE_FILM_POSTER, $film->id, CloudAsset::ASSET_IMAGE, $film->poster_url);
                    $this->registerAsset(CloudAsset::RESOURCE_FILM_TRAILER, $film->id, CloudAsset::ASSET_VIDEO, $film->trailer_url);
                }
            });

        Episode::query()
            ->select(['id', 'link'])
            ->chunkById($chunkSize, function ($episodes): void {
                foreach ($episodes as $episode) {
                    $this->registerAsset(CloudAsset::RESOURCE_EPISODE, $episode->id, CloudAsset::ASSET_VIDEO, $episode->link);
                }
            });

        $msg = "Cloud assets synced: {$this->created} created, {$this->updated} updated, {$this->queued} queued.";
        $this->info($msg);
        $this->log('info', $msg);

        return self::SUCCESS;
    }

    private function log(string $level, string $message, array $context = []): void
    {
        try {
            $logFile = 'sync_cloud_assets-' . date('Y-m-d') . '.log';
            \Illuminate\Support\Facades\Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/' . $logFile),
            ])->log($level, "[SyncCloudAssets] {$message}", $context);
        } catch (\Throwable) {
            \Illuminate\Support\Facades\Log::log($level, "[SyncCloudAssets] {$message}", $context);
        }
    }

    private function registerAsset(
        string $resourceType,
        int $resourceTypeId,
        string $assetType,
        ?string $assetUrl
    ): void {
        $assetUrl = trim((string) $assetUrl);

        if ($assetUrl === '') {
            return;
        }

        $asset = CloudAsset::query()->firstOrNew([
            'resource_type' => $resourceType,
            'resource_type_id' => $resourceTypeId,
            'asset_type' => $assetType,
        ]);

        if (! $asset->exists) {
            $asset->fill([
                'status' => CloudAsset::STATUS_PENDING,
                'asset_url' => $assetUrl,
            ])->save();
            $this->created++;

            $this->dispatchUpload($asset);
            return;
        }

        $force = (bool) $this->option('force');
        $retryFailed = (bool) $this->option('retry-failed');

        // A changed source URL needs to be uploaded again for the same logical asset.
        if ($asset->asset_url !== $assetUrl || $force) {
            $asset->fill([
                'asset_url' => $assetUrl,
                'status' => CloudAsset::STATUS_PENDING,
                'storage_file_id' => null,
                'storage_url' => null,
                'last_error' => null,
                'uploaded_at' => null,
            ])->save();
            $this->updated++;
        } elseif ($retryFailed && $asset->status === CloudAsset::STATUS_FAIL) {
            $asset->fill([
                'status' => CloudAsset::STATUS_PENDING,
                'last_error' => null,
            ])->save();
            $this->updated++;
        }

        if ($asset->status === CloudAsset::STATUS_PROGRESS) {
            return;
        }

        if ($asset->status === CloudAsset::STATUS_SUCCESS && $asset->storage_url && ! $force) {
            return;
        }

        $this->dispatchUpload($asset);
    }

    private function dispatchUpload(CloudAsset $asset): void
    {
        $alreadyInQueue = \Illuminate\Support\Facades\DB::table('jobs')
            ->where('queue', 'cloud-assets')
            ->where('payload', 'like', '%"cloudAssetId";i:' . $asset->id . ';%')
            ->exists();

        if ($alreadyInQueue) {
            return;
        }

        UploadCloudAsset::dispatch($asset->id)
            ->onConnection('database')
            ->onQueue('cloud-assets');

        $this->queued++;
    }
}
