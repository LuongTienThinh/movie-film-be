<?php

return [
    // Asset version used for cache-busting. Set ASSET_VERSION in your .env to fix a value,
    // otherwise it falls back to current timestamp (useful during development).
    'version' => env('ASSET_VERSION', time()),
];
