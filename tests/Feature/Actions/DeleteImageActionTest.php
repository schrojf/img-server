<?php

use App\Actions\DeleteImageAction;
use App\Actions\GenerateRandomHashFileNameAction;
use App\Models\Image;
use App\Support\ImageStorage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Testing\FileFactory;
use Illuminate\Support\Facades\Storage;

function fakeImage(string $fakeDisk): string
{
    $disk = Storage::disk($fakeDisk);
    $imageFile = (new FileFactory)->image('image.jpg', 10, 10);
    $filePath = (new GenerateRandomHashFileNameAction)->handle('jpg');

    throw_unless($disk->put($filePath, $imageFile->getContent()));

    return $filePath;
}

test('image was not found', function () {
    $action = new DeleteImageAction;

    expect(fn () => $action->handle(123))->toThrow(ModelNotFoundException::class);
});

test('delete image with no files', function () {
    $image = image();

    (new DeleteImageAction)->handle($image->id);

    expect(Image::find($image->id))->toBeNull();
});

test('delete image file', function () {
    Storage::fake($fakeDisk = ImageStorage::original());
    $image = imageWithFile($fakeDisk);

    Storage::disk($fakeDisk)->assertExists($image->image_file['file_name']);

    (new DeleteImageAction)->handle($image->id);

    expect(Image::find($image->id))->toBeNull();
    Storage::disk($fakeDisk)->assertMissing($image->image_file['file_name']);
});

test('delete pending variant images', function () {
    Storage::fake($fakeDisk = ImageStorage::original());
    $image = imageWithFile($fakeDisk);

    Storage::fake($variantDisk = ImageStorage::variant());
    $image->update([
        'variant_files' => [
            '_pending' => [
                ['disk' => $variantDisk, 'file_name' => $variantImage1 = fakeImage($variantDisk)],
            ],
        ],
    ]);

    Storage::disk($fakeDisk)->assertExists($image->image_file['file_name']);
    Storage::disk($variantDisk)->assertExists($variantImage1);

    (new DeleteImageAction)->handle($image->id);

    expect(Image::find($image->id))->toBeNull();
    Storage::disk($fakeDisk)->assertMissing($image->image_file['file_name']);
    Storage::disk($variantDisk)->assertMissing($variantImage1);
});

test('delete variant images', function () {
    Storage::fake($fakeDisk = ImageStorage::original());
    $image = imageWithFile($fakeDisk);

    Storage::fake($variantDisk = ImageStorage::variant());
    $image->update([
        'variant_files' => [
            'variant' => [
                'format' => ['disk' => $variantDisk, 'file_name' => $variantImage1 = fakeImage($variantDisk)],
            ],
        ],
    ]);

    Storage::disk($fakeDisk)->assertExists($image->image_file['file_name']);
    Storage::disk($variantDisk)->assertExists($variantImage1);

    (new DeleteImageAction)->handle($image->id);

    expect(Image::find($image->id))->toBeNull();
    Storage::disk($fakeDisk)->assertMissing($image->image_file['file_name']);
    Storage::disk($variantDisk)->assertMissing($variantImage1);
});
