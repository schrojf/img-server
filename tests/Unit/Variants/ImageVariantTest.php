<?php

use App\Variants\ImageVariant;
use App\Variants\Modifiers\ImageCropModifier;

test('getModifiers returns registered modifiers', function () {
    $modifier = new ImageCropModifier(100, 100);

    $variant = ImageVariant::make('test')
        ->addModifier($modifier);

    expect($variant->getModifiers())->toHaveCount(1);
    expect($variant->getModifiers()[0])->toBe($modifier);
});

test('getEncoders returns registered encoders', function () {
    $variant = ImageVariant::make('test')
        ->withDefaultEncoders();

    $encoders = $variant->getEncoders();

    expect($encoders)->toHaveKeys(['jpg', 'webp']);
});

test('ImageCropModifier toArray returns expected structure', function () {
    $modifier = new ImageCropModifier(600, 600, 'ff0000', 'top-left');

    $array = $modifier->toArray();

    expect($array)->toBe([
        'type' => 'crop',
        'width' => 600,
        'height' => 600,
        'backgroundColor' => 'ff0000',
        'position' => 'top-left',
    ]);
});

test('ImageCropModifier toArray uses defaults', function () {
    $modifier = new ImageCropModifier(80, 80);

    $array = $modifier->toArray();

    expect($array['backgroundColor'])->toBe('ffffff');
    expect($array['position'])->toBe('center');
});
