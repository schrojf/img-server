<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;
use Illuminate\Support\Facades\Storage;

readonly class ImageFile
{
    public function __construct(
        public string $disk,
        public string $fileName,
        public string $mimeType,
        public int $size,
        public int $width,
        public int $height,
    ) {}

    public function stillExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->fileName);
    }

    public function toArray(): array
    {
        return [
            'disk' => $this->disk,
            'file_name' => $this->fileName,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
        ];
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
