<?php

use App\Models\Enums\ImageStatus;
use App\Models\Image;
use App\Support\ImageStorage;
use Illuminate\Http\Testing\FileFactory;
use Illuminate\Support\Facades\Storage;

function createImageWithAdSegment(
    string $originalDisk,
    string $variantDisk,
    string $hash = 'adbe105423b6796c18ccb917973edeed98032688',
): Image {
    $l1 = substr($hash, 0, 2);
    $l2 = substr($hash, 2, 2);
    $l3 = substr($hash, 4, 2);
    $basePath = "{$l1}/{$l2}/{$l3}/{$hash}";

    $fakeContent = (new FileFactory)->image('img.jpg', 10, 10)->getContent();

    $originalPath = "{$basePath}_1.jpg";
    Storage::disk($originalDisk)->put($originalPath, $fakeContent);

    $variantJpg = "{$basePath}_1_80x80wh.jpg";
    $variantWebp = "{$basePath}_1_80x80wh.webp";
    Storage::disk($variantDisk)->put($variantJpg, $fakeContent);
    Storage::disk($variantDisk)->put($variantWebp, $fakeContent);

    $url = 'https://example.com/'.uniqid().'.jpg';

    return Image::create([
        'status' => ImageStatus::DONE,
        'uid' => hash('xxh128', $url),
        'original_url' => $url,
        'image_file' => [
            'disk' => $originalDisk,
            'file_name' => $originalPath,
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'width' => 10,
            'height' => 10,
        ],
        'variant_files' => [
            '80x80wh' => [
                'jpg' => [
                    'disk' => $variantDisk,
                    'file_name' => $variantJpg,
                    'mime_type' => 'image/jpeg',
                    'size' => 50,
                    'width' => 80,
                    'height' => 80,
                ],
                'webp' => [
                    'disk' => $variantDisk,
                    'file_name' => $variantWebp,
                    'mime_type' => 'image/webp',
                    'size' => 40,
                    'width' => 80,
                    'height' => 80,
                ],
            ],
        ],
        'downloaded_at' => now(),
        'processed_at' => now(),
    ]);
}

test('renames files with ad in first segment', function () {
    Storage::fake($originalDisk = ImageStorage::original());
    Storage::fake($variantDisk = ImageStorage::variant());

    $image = createImageWithAdSegment($originalDisk, $variantDisk);

    $this->artisan('images:rename-ad-segments')->assertSuccessful();

    $image->refresh();

    // Old files should be gone
    Storage::disk($originalDisk)->assertMissing('ad/be/10/adbe105423b6796c18ccb917973edeed98032688_1.jpg');
    Storage::disk($variantDisk)->assertMissing('ad/be/10/adbe105423b6796c18ccb917973edeed98032688_1_80x80wh.jpg');
    Storage::disk($variantDisk)->assertMissing('ad/be/10/adbe105423b6796c18ccb917973edeed98032688_1_80x80wh.webp');

    // New files should exist
    Storage::disk($originalDisk)->assertExists('ia/be/10/iabe105423b6796c18ccb917973edeed98032688_1.jpg');
    Storage::disk($variantDisk)->assertExists('ia/be/10/iabe105423b6796c18ccb917973edeed98032688_1_80x80wh.jpg');
    Storage::disk($variantDisk)->assertExists('ia/be/10/iabe105423b6796c18ccb917973edeed98032688_1_80x80wh.webp');

    // DB should have updated filenames
    expect($image->image_file['file_name'])
        ->toBe('ia/be/10/iabe105423b6796c18ccb917973edeed98032688_1.jpg')
        ->and($image->variant_files['80x80wh']['jpg']['file_name'])
        ->toBe('ia/be/10/iabe105423b6796c18ccb917973edeed98032688_1_80x80wh.jpg')
        ->and($image->variant_files['80x80wh']['webp']['file_name'])
        ->toBe('ia/be/10/iabe105423b6796c18ccb917973edeed98032688_1_80x80wh.webp');

    // Pending flag should be removed
    expect($image->image_file)->not->toHaveKey('_rename_pending');
});

test('renames files with ad in second segment', function () {
    Storage::fake($originalDisk = ImageStorage::original());
    Storage::fake($variantDisk = ImageStorage::variant());

    // Hash where second segment (positions 2-3) is 'ad'
    $image = createImageWithAdSegment($originalDisk, $variantDisk, '12ad56789012345678901234567890abcdef1234');

    $this->artisan('images:rename-ad-segments')->assertSuccessful();

    $image->refresh();

    Storage::disk($originalDisk)->assertExists('12/ia/56/12ia56789012345678901234567890abcdef1234_1.jpg');

    expect($image->image_file['file_name'])
        ->toBe('12/ia/56/12ia56789012345678901234567890abcdef1234_1.jpg');
});

test('renames files with ad in third segment', function () {
    Storage::fake($originalDisk = ImageStorage::original());
    Storage::fake($variantDisk = ImageStorage::variant());

    // Hash where third segment (positions 4-5) is 'ad'
    $image = createImageWithAdSegment($originalDisk, $variantDisk, '1234ad789012345678901234567890abcdef1234');

    $this->artisan('images:rename-ad-segments')->assertSuccessful();

    $image->refresh();

    Storage::disk($originalDisk)->assertExists('12/34/ia/1234ia789012345678901234567890abcdef1234_1.jpg');

    expect($image->image_file['file_name'])
        ->toBe('12/34/ia/1234ia789012345678901234567890abcdef1234_1.jpg');
});

test('skips images without ad segments', function () {
    Storage::fake($originalDisk = ImageStorage::original());
    Storage::fake($variantDisk = ImageStorage::variant());

    $image = createImageWithAdSegment($originalDisk, $variantDisk, '1234567890abcdef1234567890abcdef12345678');

    $this->artisan('images:rename-ad-segments')->assertSuccessful();

    $image->refresh();

    // Filenames should be unchanged
    expect($image->image_file['file_name'])
        ->toBe('12/34/56/1234567890abcdef1234567890abcdef12345678_1.jpg');

    Storage::disk($originalDisk)->assertExists('12/34/56/1234567890abcdef1234567890abcdef12345678_1.jpg');
});

test('dry run does not modify files or database', function () {
    Storage::fake($originalDisk = ImageStorage::original());
    Storage::fake($variantDisk = ImageStorage::variant());

    $image = createImageWithAdSegment($originalDisk, $variantDisk);

    $this->artisan('images:rename-ad-segments', ['--dry-run' => true])->assertSuccessful();

    $image->refresh();

    // Old files should still exist
    Storage::disk($originalDisk)->assertExists('ad/be/10/adbe105423b6796c18ccb917973edeed98032688_1.jpg');

    // DB should be unchanged
    expect($image->image_file['file_name'])
        ->toBe('ad/be/10/adbe105423b6796c18ccb917973edeed98032688_1.jpg')
        ->and($image->image_file)->not->toHaveKey('_rename_pending');
});

test('resumes interrupted rename on next run', function () {
    Storage::fake($originalDisk = ImageStorage::original());
    Storage::fake($variantDisk = ImageStorage::variant());

    $image = createImageWithAdSegment($originalDisk, $variantDisk);

    // Simulate a crash: save pending flag to DB but don't rename files
    $imageFile = $image->image_file;
    $imageFile['_rename_pending'] = [
        [
            'disk' => $originalDisk,
            'old' => 'ad/be/10/adbe105423b6796c18ccb917973edeed98032688_1.jpg',
            'new' => 'ia/be/10/iabe105423b6796c18ccb917973edeed98032688_1.jpg',
        ],
        [
            'disk' => $variantDisk,
            'old' => 'ad/be/10/adbe105423b6796c18ccb917973edeed98032688_1_80x80wh.jpg',
            'new' => 'ia/be/10/iabe105423b6796c18ccb917973edeed98032688_1_80x80wh.jpg',
        ],
        [
            'disk' => $variantDisk,
            'old' => 'ad/be/10/adbe105423b6796c18ccb917973edeed98032688_1_80x80wh.webp',
            'new' => 'ia/be/10/iabe105423b6796c18ccb917973edeed98032688_1_80x80wh.webp',
        ],
    ];
    $image->update(['image_file' => $imageFile]);

    // Run command — should resume the interrupted rename
    $this->artisan('images:rename-ad-segments')->assertSuccessful();

    $image->refresh();

    Storage::disk($originalDisk)->assertExists('ia/be/10/iabe105423b6796c18ccb917973edeed98032688_1.jpg');
    Storage::disk($originalDisk)->assertMissing('ad/be/10/adbe105423b6796c18ccb917973edeed98032688_1.jpg');

    expect($image->image_file['file_name'])
        ->toBe('ia/be/10/iabe105423b6796c18ccb917973edeed98032688_1.jpg')
        ->and($image->image_file)->not->toHaveKey('_rename_pending');
});

test('resumes partially completed rename', function () {
    Storage::fake($originalDisk = ImageStorage::original());
    Storage::fake($variantDisk = ImageStorage::variant());

    $image = createImageWithAdSegment($originalDisk, $variantDisk);

    // Simulate partial crash: one file already renamed, others not yet
    Storage::disk($originalDisk)->move(
        'ad/be/10/adbe105423b6796c18ccb917973edeed98032688_1.jpg',
        'ia/be/10/iabe105423b6796c18ccb917973edeed98032688_1.jpg',
    );

    $imageFile = $image->image_file;
    $imageFile['_rename_pending'] = [
        [
            'disk' => $originalDisk,
            'old' => 'ad/be/10/adbe105423b6796c18ccb917973edeed98032688_1.jpg',
            'new' => 'ia/be/10/iabe105423b6796c18ccb917973edeed98032688_1.jpg',
        ],
        [
            'disk' => $variantDisk,
            'old' => 'ad/be/10/adbe105423b6796c18ccb917973edeed98032688_1_80x80wh.jpg',
            'new' => 'ia/be/10/iabe105423b6796c18ccb917973edeed98032688_1_80x80wh.jpg',
        ],
        [
            'disk' => $variantDisk,
            'old' => 'ad/be/10/adbe105423b6796c18ccb917973edeed98032688_1_80x80wh.webp',
            'new' => 'ia/be/10/iabe105423b6796c18ccb917973edeed98032688_1_80x80wh.webp',
        ],
    ];
    $image->update(['image_file' => $imageFile]);

    $this->artisan('images:rename-ad-segments')->assertSuccessful();

    $image->refresh();

    // All files should now be at new paths
    Storage::disk($originalDisk)->assertExists('ia/be/10/iabe105423b6796c18ccb917973edeed98032688_1.jpg');
    Storage::disk($variantDisk)->assertExists('ia/be/10/iabe105423b6796c18ccb917973edeed98032688_1_80x80wh.jpg');
    Storage::disk($variantDisk)->assertExists('ia/be/10/iabe105423b6796c18ccb917973edeed98032688_1_80x80wh.webp');

    expect($image->image_file)->not->toHaveKey('_rename_pending');
});

test('only processes done images', function () {
    Storage::fake($originalDisk = ImageStorage::original());
    Storage::fake($variantDisk = ImageStorage::variant());

    $image = createImageWithAdSegment($originalDisk, $variantDisk);
    $image->update(['status' => ImageStatus::FAILED]);

    $this->artisan('images:rename-ad-segments')->assertSuccessful();

    $image->refresh();

    // Should not have been renamed since status is FAILED
    expect($image->image_file['file_name'])
        ->toBe('ad/be/10/adbe105423b6796c18ccb917973edeed98032688_1.jpg');
});
