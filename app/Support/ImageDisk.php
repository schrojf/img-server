<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use ValueError;

class ImageDisk
{
    public static function original(): Filesystem
    {
        $disk = config('images.disk.original');

        if (! is_string($disk) || empty($disk)) {
            throw new ValueError('Config "images.disk.original" must be a string.');
        }

        return Storage::disk($disk);
    }

    public static function variant(): Filesystem
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
     * @param string $name
     * @return bool
     */
    public static function isConfigured(string $name): bool
    {
        try {
            Storage::disk($name);

            return true;
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
