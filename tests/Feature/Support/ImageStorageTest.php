<?php

use App\Support\ImageStorage;
use Illuminate\Contracts\Filesystem\Filesystem;

test('disk methods', function () {
    expect(ImageStorage::original())->toBe(config('images.disk.original'))
        ->and(ImageStorage::variant())->toBe(config('images.disk.variant'));
});

test('storage methods', function () {
    expect(ImageStorage::originalDisk())->toBeInstanceOf(Filesystem::class)
        ->and(ImageStorage::variantDisk())->toBeInstanceOf(Filesystem::class);
});

test('images disks are configured', function () {
    expect(ImageStorage::isConfigured(config('images.disk.original')))->toBeTrue()
        ->and(ImageStorage::isConfigured(config('images.disk.variant')))->toBeTrue();
});

test('invalid disk will return false', function () {
    expect(ImageStorage::isConfigured('non-existing-disk-name'))->toBeFalse();
});
