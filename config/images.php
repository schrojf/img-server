<?php

return [

    'disk' => [
        'original' => 'downloaded',
        'variant' => 'converted',
    ],

    'downloads' => [
        'allowedExtensions' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
        'maxFileSize' => 30_000_000,
    ],

    'driver' => env('INTERVENTION_IMAGE_DRIVE', 'Gd'),

    'avif' => env('AVIF_ENABLED', false),

];
