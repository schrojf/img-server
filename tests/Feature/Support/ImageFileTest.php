<?php

namespace Tests\Feature\Storage;

use App\Support\ImageFile;
use Illuminate\Support\Facades\Storage;

test('invalid file', function () {
    Storage::fake('local');

    $file = new ImageFile('local', 'file.bin', 'unknown', 0, 0, 0);

    expect($file->stillExists())->toBeFalse()
        ->and($file->url())->toBeNull();
});

test('valid file', function () {
    Storage::fake('local')
        ->put('a/b/c/file.bin', 'content');

    $file = new ImageFile('local', 'a/b/c/file.bin', 'unknown', 0, 0, 0);

    expect($file->stillExists())->toBeTrue()
        ->and($file->url())->toBeNull();
});

test('valid public file', function () {
    Storage::fake('local', [
        'url' => 'http://localhost',
    ])
        ->put('a/b/c/file.bin', 'content');

    $file = new ImageFile('local', 'a/b/c/file.bin', 'unknown', 0, 0, 0);

    expect($file->stillExists())->toBeTrue()
        ->and($file->url())->toBe('http://localhost/a/b/c/file.bin');
});

test('valid private file', function () {
    Storage::fake('local', [
        'visibility' => 'private',
        'url' => 'http://localhost',
    ])
        ->put('a/b/c/file.bin', 'content');

    $file = new ImageFile('local', 'a/b/c/file.bin', 'unknown', 0, 0, 0);

    expect($file->stillExists())->toBeTrue()
        ->and($file->url())->toBeNull();
});

test('toArray method', function () {
    Storage::fake('local');

    $file = new ImageFile('local', 'file.bin', 'text/unknown', 10, 30, 20);

    expect($file->toArray())->toMatchArray([
        'disk' => 'local',
        'file_name' => 'file.bin',
        'mime_type' => 'text/unknown',
        'size' => 10,
        'width' => 30,
        'height' => 20,
    ]);
});
