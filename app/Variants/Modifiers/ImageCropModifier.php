<?php

namespace App\Variants\Modifiers;

use Intervention\Image\Image;

class ImageCropModifier extends ImageModifier
{
    public function __construct(
        protected readonly int $width,
        protected readonly int $height,
        protected readonly string $backgroundColor = 'ffffff',
        protected readonly string $position = 'center',
    ) {
        $this->validateDimensions($width, $height);
    }

    protected function validateDimensions(int $width, int $height): void
    {
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('Width and height must be positive integers.');
        }
    }

    public function apply(Image $image): Image
    {
        return $image->pad($this->width, $this->height, $this->backgroundColor, $this->position);
    }
}
