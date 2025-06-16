<?php

use App\Actions\GenerateRandomHashFileNameAction;
use App\Models\Enums\ImageStatus;
use App\Models\Image;
use App\Support\ImageFile;
use App\Support\ImageStorage;
use App\Variants\GenerateVariantsAction;
use App\Variants\ImageVariant;
use App\Variants\ImageVariantRegistry;
use Illuminate\Http\Testing\FileFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

function imageWithFile(?string $fakeDisk = null): Image
{
    if (is_null($fakeDisk)) {
        $fakeDisk = ImageStorage::original();
    }

    $disk = Storage::disk($fakeDisk);
    $imageFile = (new FileFactory)->image('image.jpg', 10, 10);
    $filePath = (new GenerateRandomHashFileNameAction)->handle('jpg');

    throw_unless($disk->put($filePath, $imageFile->getContent()));

    $imageFile = new ImageFile(
        $fakeDisk,
        $filePath,
        'image/jpg',
        $imageFile->getSize(),
        10,
        10,
    );

    $url = 'https://example.org/image_'.Str::random(6).'.jpg';

    $image = Image::create([
        'status' => ImageStatus::IMAGE_DOWNLOADED,
        'original_url' => $url,
        'uid' => hash('xxh128', $url),
        'image_file' => $imageFile->toArray(),
    ]);

    return $image;
}

test('image variants are generated and persisted on to the disk', function () {
    Storage::fake($fakeDisk = ImageStorage::original());
    $imageModel = imageWithFile($fakeDisk);

    $job = new GenerateVariantsAction(app(ImageManager::class));

    Storage::fake(ImageStorage::variant());

    $variants = $job->handle($imageModel);

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

    expect($imageModel->status)->toBe(ImageStatus::IMAGE_DOWNLOADED) // Image state is keep unchanged
        ->and($imageModel->last_error)->toBeNull()
        ->and($variants)->toHaveCount(count(ImageVariantRegistry::all()));
});
