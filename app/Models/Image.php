<?php

namespace App\Models;

use App\Models\Enums\ImageStatus;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $table = 'images';

    protected $fillable = [
        'status',
        'uid',
        'original_url',
        'image_file',
        'variant_files',
        'last_error',
        'downloaded_at',
        'processed_at',
    ];

    protected $casts = [
        'status' => ImageStatus::class,
        'image_file' => 'array',
        'variant_files' => 'array',
        'downloaded_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
