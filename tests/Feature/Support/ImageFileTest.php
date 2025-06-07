<?php

namespace Tests\Feature\Storage;

use App\Support\ImageFile;
use Illuminate\Support\Facades\Storage;

test('invalid file', function () {
    Storage::fake('local');

    $file = new ImageFile('local', 'file.bin', 'unknown', 0);

    expect($file->stillExists())->toBeFalse();
});

test('valid file', function () {
    Storage::fake('local')
        ->put('a/b/c/file.bin', 'content');

    $file = new ImageFile('local', 'a/b/c/file.bin', 'unknown', 0);

    expect($file->stillExists())->toBeTrue();
});

test('valid public file', function () {
    Storage::fake('local', [
        'url' => 'http://localhost',
    ])
        ->put('a/b/c/file.bin', 'content');

    $file = new ImageFile('local', 'a/b/c/file.bin', 'unknown', 0);

    expect($file->stillExists())->toBeTrue();
});

test('valid private file', function () {
    Storage::fake('local', [
        'visibility' => 'private',
        'url' => 'http://localhost',
    ])
        ->put('a/b/c/file.bin', 'content');

    $file = new ImageFile('local', 'a/b/c/file.bin', 'unknown', 0);

    expect($file->stillExists())->toBeTrue();
});
