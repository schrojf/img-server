<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use ValueError;

class ImageStorage
{
    public static function original(): string
    {
        $disk = config('images.disk.original');

        if (! is_string($disk) || empty($disk)) {
            throw new ValueError('Config "images.disk.original" must be a string.');
        }

        return $disk;
    }

    public static function originalDisk(): Filesystem
    {
        return Storage::disk(static::original());
    }

    public static function variant(): string
    {
        $disk = config('images.disk.variant');

        if (! is_string($disk) || empty($disk)) {
            throw new ValueError('Config "images.disk.variant" must be a string.');
        }

        return $disk;
    }

    public static function variantDisk(): Filesystem
    {
        return Storage::disk(static::variant());
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
