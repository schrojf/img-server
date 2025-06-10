<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use ValueError;

class ImageStorage
{
    public static function original(): string
    {
        return static::diskName('original');
    }

    public static function originalDisk(): Filesystem
    {
        return Storage::disk(static::original());
    }

    public static function variant(): string
    {
        return static::diskName('variant');
    }

    public static function variantDisk(): Filesystem
    {
        return Storage::disk(static::variant());
    }

    protected static function diskName(string $config): string
    {
        $disk = config("images.disk.{$config}");

        if (! is_string($disk) || empty($disk)) {
            throw new ValueError("Config \"images.disk.{$config}\" must be a string.");
        }

        return $disk;
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
