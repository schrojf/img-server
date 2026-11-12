<?php

use App\Actions\GenerateRandomHashFileNameAction;
use App\Actions\GenerateVariantsAction;
use App\Exceptions\InvalidImageValueException;
use App\Models\Enums\ImageStatus;
use App\Models\Image;
use App\Support\ImageFile;
use App\Support\ImageStorage;
use App\Variants\ImageVariant;
use App\Variants\ImageVariantRegistry;
use Illuminate\Http\Testing\FileFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

function imageWithFile(?string $fakeDisk = null): Image
{
    $imageFile = (new FileFactory)->image('image.jpg', 10, 10);

    return imageWithContents($imageFile->getContent(), 'jpg', 10, 10, $fakeDisk);
}

function imageWithContents(string $contents, string $extension, int $width, int $height, ?string $fakeDisk = null): Image
{
    if (is_null($fakeDisk)) {
        $fakeDisk = ImageStorage::original();
    }

    $disk = Storage::disk($fakeDisk);
    $filePath = (new GenerateRandomHashFileNameAction)->handle($extension);

    throw_unless($disk->put($filePath, $contents));

    $imageFile = new ImageFile(
        $fakeDisk,
        $filePath,
        'image/'.$extension,
        strlen($contents),
        $width,
        $height,
    );

    $url = 'https://example.org/image_'.Str::random(6).'.'.$extension;

    $image = Image::create([
        'status' => ImageStatus::QUEUED,
        'original_url' => $url,
        'uid' => hash('xxh128', $url),
        'image_file' => $imageFile->toArray(),
    ]);

    return $image;
}

/**
 * Reads a pixel of a generated variant with plain GD, independently of Intervention Image.
 *
 * @return array{red: int, green: int, blue: int, alpha: int} alpha: 0 = opaque, 127 = transparent
 */
function variantPixel(ImageFile $file, int $x, int $y): array
{
    $gd = imagecreatefromstring(Storage::disk($file->disk)->get($file->fileName));

    return imagecolorsforindex($gd, imagecolorat($gd, $x, $y));
}

test('image variants are generated and persisted on to the disk', function () {
    Storage::fake($fakeDisk = ImageStorage::original());
    $imageModel = imageWithFile($fakeDisk);

    $job = new GenerateVariantsAction(app(ImageManager::class));

    Storage::fake(ImageStorage::variant());

    $variants = $job->handle($imageModel->id);

    foreach ($variants as $variant) {
        /** @var ImageFile $subVariant */
        foreach ($variant as $subVariant) {
            Storage::disk(ImageStorage::variant())->assertExists($subVariant->fileName);
        }
    }

    /** @var ImageVariant $variant */
    foreach (ImageVariantRegistry::all() as $variant) {
        $expectedEndings = $variant->simulatedEncodedFiles('');

        expect($variants[$variant->variantName])->toHaveCount(count($expectedEndings));

        foreach ($expectedEndings as $ending) {
            $match = collect($variants[$variant->variantName])->first(function ($imageFile) use ($ending) {
                return str_ends_with($imageFile->fileName, $ending);
            });

            expect($match)->not()->toBeNull();
        }
    }

    $imageModel->refresh();

    expect($imageModel->status)->toBe(ImageStatus::DONE)
        ->and($imageModel->last_error)->toBeNull()
        ->and($variants)->toHaveCount(count(ImageVariantRegistry::all()));
});

test('exception will be caught and save last error', function () {
    $image = image();
    $image->update(['status' => ImageStatus::QUEUED]);

    $action = new GenerateVariantsAction(app(ImageManager::class));

    expect(fn () => $action->handle($image->id))->toThrow(InvalidImageValueException::class);

    $image->refresh();
});

test('transparent areas are flattened onto the background color in every format', function () {
    Storage::fake($fakeDisk = ImageStorage::original());
    Storage::fake(ImageStorage::variant());

    // 400x200 PNG: left half fully transparent, right half opaque blue.
    $gd = imagecreatetruecolor(400, 200);
    imagealphablending($gd, false);
    imagesavealpha($gd, true);
    imagefill($gd, 0, 0, imagecolorallocatealpha($gd, 0, 0, 0, 127));
    imagefilledrectangle($gd, 200, 0, 399, 199, imagecolorallocate($gd, 0, 0, 255));
    ob_start();
    imagepng($gd);
    $png = ob_get_clean();

    $imageModel = imageWithContents($png, 'png', 400, 200, $fakeDisk);

    $variants = (new GenerateVariantsAction(app(ImageManager::class)))->handle($imageModel->id);

    // In the 300x300 variant the source is scaled to 300x150 and centered vertically.
    foreach (['jpg', 'webp'] as $extension) {
        $file = $variants['300x300wh'][$extension];

        $padding = variantPixel($file, 150, 20);
        $transparent = variantPixel($file, 75, 150);
        $blue = variantPixel($file, 225, 150);

        foreach ([$padding, $transparent] as $pixel) {
            expect($pixel['alpha'])->toBe(0)
                ->and($pixel['red'])->toBeGreaterThan(235)
                ->and($pixel['green'])->toBeGreaterThan(235)
                ->and($pixel['blue'])->toBeGreaterThan(235);
        }

        expect($blue['alpha'])->toBe(0)
            ->and($blue['red'])->toBeLessThan(60)
            ->and($blue['green'])->toBeLessThan(60)
            ->and($blue['blue'])->toBeGreaterThan(200);
    }
});

test('cmyk source images are converted to rgb variants', function () {
    Storage::fake($fakeDisk = ImageStorage::original());
    Storage::fake(ImageStorage::variant());

    $imagick = new Imagick;
    $imagick->newImage(400, 200, new ImagickPixel('rgb(255, 0, 0)'));
    $imagick->transformImageColorspace(Imagick::COLORSPACE_CMYK);
    $imagick->setImageFormat('jpeg');

    $imageModel = imageWithContents($imagick->getImageBlob(), 'jpg', 400, 200, $fakeDisk);

    $variants = (new GenerateVariantsAction(app(ImageManager::class)))->handle($imageModel->id);

    foreach (['jpg', 'webp'] as $extension) {
        $file = $variants['300x300wh'][$extension];

        $padding = variantPixel($file, 150, 20);
        $red = variantPixel($file, 150, 150);

        expect($padding['red'])->toBeGreaterThan(235)
            ->and($padding['green'])->toBeGreaterThan(235)
            ->and($padding['blue'])->toBeGreaterThan(235)
            ->and($red['red'])->toBeGreaterThan(200)
            ->and($red['green'])->toBeLessThan(80)
            ->and($red['blue'])->toBeLessThan(80);
    }
})->skip(! extension_loaded('imagick'), 'The imagick extension is required to create a CMYK fixture.');
