<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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

class CheckForSupportedImageFormatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:supported-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for supported image formats';

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

    /**
     * Execute the console command.
     */
    public function handle(ImageManager $imageManager): int
    {
        $this->warn('Checking for supported image formats...');

        $file = UploadedFile::fake()->image('image.jpg');
        $image = $imageManager->read($file->getRealPath());

        $supported = [];
        foreach (static::$formats as $mime => [$encoder, $extension]) {
            try {
                (clone $image)->encode(new $encoder);
                $supported[] = [$mime, $extension, '✅️', null];
            } catch (NotSupportedException $e) {
                $supported[] = [$mime, $extension, '❌', $e->getMessage()];
            } catch (\Exception $e) {
                $supported[] = [$mime, $extension, '💣', $e->getMessage()];
            }
        }

        $this->table(['MIME Type', 'Extension', 'Supported', 'Message'], $supported);

        return 0;
    }
}
