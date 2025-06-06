<?php

use App\Support\ImageDisk;
use Illuminate\Contracts\Filesystem\Filesystem;

test('storage methods', function () {
    expect(ImageDisk::original())->toBeInstanceOf(Filesystem::class)
        ->and(ImageDisk::variant())->toBeInstanceOf(Filesystem::class);
});

test('images disks are configured', function () {
    expect(ImageDisk::isConfigured(config('images.disk.original')))->toBeTrue()
        ->and(ImageDisk::isConfigured(config('images.disk.variant')))->toBeTrue();
});

test('invalid disk will return false', function () {
    expect(ImageDisk::isConfigured('non-existing-disk-name'))->toBeFalse();
});
