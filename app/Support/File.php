<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Storage;

abstract readonly class File implements Arrayable
{
    public string $disk;

    public string $fileName;

    abstract public static function fromArray(array $data): self;

    abstract public function toArray(): array;

    public function storage(): FilesystemContract
    {
        return Storage::disk($this->disk);
    }

    public function stillExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->fileName);
    }

    public function fullPath(): string
    {
        return Storage::disk($this->disk)->path($this->fileName);
    }

    public function url(): ?string
    {
        $storage = Storage::disk($this->disk);

        if (! $storage->exists($this->fileName)) {
            return null;
        }

        if ($storage->getVisibility($this->fileName) == FilesystemContract::VISIBILITY_PRIVATE) {
            return null;
        }

        if (! isset($storage->getConfig()['url'])) {
            return null;
        }

        return Storage::disk($this->disk)->url($this->fileName);
    }
}
