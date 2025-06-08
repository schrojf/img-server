<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use ValueError;

class ImageStorage
{
    public static function originalDisk(): Filesystem
    {
        $disk = config('images.disk.original');

        if (! is_string($disk) || empty($disk)) {
            throw new ValueError('Config "images.disk.original" must be a string.');
        }

        return Storage::disk($disk);
    }

    public static function variantDisk(): Filesystem
    {
        $disk = config('images.disk.variant');

        if (! is_string($disk) || empty($disk)) {
            throw new ValueError('Config "images.disk.variant" must be a string.');
        }

        return Storage::disk($disk);
    }

    /**
     * Determine whether given disk name is configured storage disk.
     *
     * @param string $disk
     * @return bool
     */
    public static function isConfigured(string $disk): bool
    {
        try {
            Storage::disk($disk);

            return true;
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
