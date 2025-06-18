<?php

namespace App\Variants;

use App\Exceptions\ImageVariantGenerationException;
use App\Variants\Modifiers\ImageModifier;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Image;
use Intervention\Image\Interfaces\EncoderInterface;

class ImageVariant
{
    protected array $modifiers = [];

    protected array $encoders = [];

    public function __construct(public readonly string $variantName) {}

    public static function make(string $variantName): static
    {
        $sanitized = Str::slug($variantName);

        return new static($sanitized);
    }

    public function addModifier(ImageModifier $modifier): static
    {
        $this->modifiers[] = $modifier;

        return $this;
    }

    public function addEncoder(string $extension, EncoderInterface $encoder): static
    {
        $this->encoders[$extension] = $encoder;

        return $this;
    }

    public function withDefaultEncoders(): static
    {
        return $this
            ->addEncoder('jpg', new JpegEncoder(quality: 75, progressive: true, strip: true))
            ->addEncoder('webp', new WebpEncoder(quality: 65, strip: true));
    }

    public function withAvifEncoder(): static
    {
        if (config('images.avif')) {
            return $this->addEncoder('avif', new AvifEncoder(quality: 50, strip: true));
        }

        return $this;
    }

    public function simulatedEncodedFiles(string $filePathWithoutExtension): array
    {
        return PendingEncodings::simulateFileEncoding(
            $filePathWithoutExtension,
            $this->variantName,
            $this->encoders,
        );
    }

    public function process(Image $image): PendingEncodings
    {
        $image = clone $image;

        foreach ($this->modifiers as $modifier) {
            try {
                $modifier->apply($image);
            } catch (\Throwable $e) {
                throw ImageVariantGenerationException::modifierFailed(
                    get_class($modifier),
                    $this->variantName,
                    $e
                );
            }
        }

        return $this->prepareEncodings($image);
    }

    protected function prepareEncodings(Image $image): PendingEncodings
    {
        return new PendingEncodings(
            image: $image,
            variantName: $this->variantName,
            encoders: $this->encoders,
        );
    }
}
