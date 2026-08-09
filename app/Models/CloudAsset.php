<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CloudAsset extends Model
{
    use HasFactory;

    public const STATUS_FAIL = 'fail';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROGRESS = 'progress';
    public const STATUS_SUCCESS = 'success';

    public const RESOURCE_FILM_THUMBNAIL = 'film_thumbnail';
    public const RESOURCE_FILM_POSTER = 'film_poster';
    public const RESOURCE_FILM_TRAILER = 'film_trailer';
    public const RESOURCE_EPISODE = 'episode';

    public const ASSET_IMAGE = 'image';
    public const ASSET_VIDEO = 'video';

    protected $fillable = [
        'status',
        'resource_type_id',
        'resource_type',
        'asset_type',
        'asset_url',
        'storage_url',
        'storage_file_id',
        'attempts',
        'last_error',
        'uploaded_at',
    ];
}
