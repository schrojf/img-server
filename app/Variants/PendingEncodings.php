<?php

namespace App\Variants;

use Intervention\Image\Image;

class PendingEncodings
{
    public function __construct(
        public readonly Image $image,
        public readonly string $variantName,
        public readonly array $encoders,
    ) {}

    public static function simulateFileEncoding(
        string $filePathWithoutExtension,
        string $variantName,
        array $encoders,
    ): array {
        $filePathWithoutExtension .= '_'.$variantName;

        $toBeEncoded = [];
        foreach ($encoders as $extension => $encoder) {
            $toBeEncoded[] = $filePathWithoutExtension.'.'.$extension;
        }

        return $toBeEncoded;
    }
}
