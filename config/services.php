<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'google_drive' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_DRIVE_REDIRECT_URI'),
        'token_path' => env('GOOGLE_DRIVE_TOKEN_PATH', storage_path('app/private/google-drive-token.json')),
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
        'folders' => [
            'posters' => env('GOOGLE_DRIVE_POSTERS_FOLDER_ID'),
            'thumbnails' => env('GOOGLE_DRIVE_THUMBNAILS_FOLDER_ID'),
            'video' => env('GOOGLE_DRIVE_VIDEO_FOLDER_ID'),
        ],
        'public' => env('GOOGLE_DRIVE_PUBLIC', false),
        'chunk_size' => (int) env('GOOGLE_DRIVE_CHUNK_SIZE', 10 * 1024 * 1024),
    ],

    'cloud_assets' => [
        'min_video_bytes' => (int) env('CLOUD_ASSET_MIN_VIDEO_BYTES', 512 * 1024),
        'min_image_bytes' => (int) env('CLOUD_ASSET_MIN_IMAGE_BYTES', 1024),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
        'key'  => env('MEILISEARCH_KEY'),
    ],

];
