<?php

return [

    'disk' => [
        'original' => 'downloaded',
        'variant' => 'converted',
    ],

    'downloads' => [
        'allowedExtensions' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
        'maxFileSize' => 30 * 1024 * 1024, // 30 MB
        'timeout' => 120, // seconds
        'retries' => 3,
        'baseBackoffMs' => 200,
        'userAgent' => 'ImageServer Downloader',
        'tmpPrefix' => 'image-server-',
    ],

    'jobs' => [
        'dispatch' => 'chain', // 'sync', 'batch', 'chain' or 'null'
        'autoExpire' => false,
    ],

    'driver' => env('INTERVENTION_IMAGE_DRIVE', 'Gd'),

    'avif' => env('AVIF_ENABLED', false),

];
