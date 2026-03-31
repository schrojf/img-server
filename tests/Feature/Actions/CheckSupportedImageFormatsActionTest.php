<?php

use App\Actions\CheckSupportedImageFormatsAction;

test('it returns an array of format check results', function () {
    $action = app(CheckSupportedImageFormatsAction::class);
    $results = $action->handle();

    expect($results)->toBeArray()->not->toBeEmpty();

    foreach ($results as $result) {
        expect($result)->toHaveKeys(['mime', 'extension', 'supported', 'message']);
        expect($result['mime'])->toBeString();
        expect($result['extension'])->toBeString();
        expect($result['supported'])->toBeBool();
    }
});

test('jpeg is always supported', function () {
    $action = app(CheckSupportedImageFormatsAction::class);
    $results = $action->handle();

    $jpeg = collect($results)->firstWhere('mime', 'image/jpeg');

    expect($jpeg)->not->toBeNull();
    expect($jpeg['supported'])->toBeTrue();
    expect($jpeg['extension'])->toBe('jpg');
});

test('it checks all known formats', function () {
    $action = app(CheckSupportedImageFormatsAction::class);
    $results = $action->handle();

    $mimeTypes = array_column($results, 'mime');

    expect($mimeTypes)->toContain('image/jpeg');
    expect($mimeTypes)->toContain('image/png');
    expect($mimeTypes)->toContain('image/webp');
    expect($mimeTypes)->toContain('image/avif');
});
