<?php

namespace App\Actions;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\BmpEncoder;
use Intervention\Image\Encoders\GifEncoder;
use Intervention\Image\Encoders\HeicEncoder;
use Intervention\Image\Encoders\Jpeg2000Encoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\TiffEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Exceptions\NotSupportedException;
use Intervention\Image\ImageManager;

class CheckSupportedImageFormatsAction
{
    public static array $formats = [
        'image/jpeg' => [JpegEncoder::class, 'jpg'],
        'image/jp2' => [Jpeg2000Encoder::class, 'jp2'],
        'image/png' => [PngEncoder::class, 'png'],
        'image/gif' => [GifEncoder::class, 'gif'],
        'image/webp' => [WebpEncoder::class, 'webp'],
        'image/avif' => [AvifEncoder::class, 'avif'],
        'image/tiff' => [TiffEncoder::class, 'tiff'],
        'image/bmp' => [BmpEncoder::class, 'bmp'],
        'image/heic' => [HeicEncoder::class, 'heic'],
    ];

    public function __construct(
        protected ImageManager $imageManager,
    ) {}

    /**
     * @return array<int, array{mime: string, extension: string, supported: bool, message: string|null}>
     */
    public function handle(): array
    {
        $file = UploadedFile::fake()->image('image.jpg');
        $image = $this->imageManager->read($file->getRealPath());

        $results = [];
        foreach (static::$formats as $mime => [$encoder, $extension]) {
            try {
                (clone $image)->encode(new $encoder);
                $results[] = ['mime' => $mime, 'extension' => $extension, 'supported' => true, 'message' => null];
            } catch (NotSupportedException $e) {
                $results[] = ['mime' => $mime, 'extension' => $extension, 'supported' => false, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                $results[] = ['mime' => $mime, 'extension' => $extension, 'supported' => false, 'message' => $e->getMessage()];
            }
        }

        return $results;
    }
}
