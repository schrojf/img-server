<?php

namespace App\Support;

use App\Models\Image;

final readonly class ImageFile extends File
{
    public function __construct(
        public string $disk,
        public string $fileName,
        public string $mimeType,
        public int $size,
        public int $width,
        public int $height,
    ) {}

    public static function fromModel(Image $image): ImageFile
    {
        return self::fromArray($image->image_file);
    }

    public static function fromArray(array $data): ImageFile
    {
        return new self(
            $data['disk'],
            $data['file_name'],
            $data['mime_type'],
            $data['size'],
            $data['width'],
            $data['height'],
        );
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
}
