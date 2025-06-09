<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $table = 'images';

    protected $fillable = [
        'uid',
        'original_url',
        'image_file',
    ];

    protected $casts = [
        'image_file' => 'array',
    ];
}
