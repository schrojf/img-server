<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

readonly class ImageFile
{
    public function __construct(
        public string $disk,
        public string $fileName,
        public string $mimeType,
        public int $size,
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
        ];
    }
}
