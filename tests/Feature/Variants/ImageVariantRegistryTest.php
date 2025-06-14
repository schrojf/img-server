<?php

use App\Variants\ImageVariantRegistry;

test('service provider is called before test', function () {
    expect(ImageVariantRegistry::all())->not->toBeEmpty();
});

test('calling two time will throw no exception', function () {
    ImageVariantRegistry::all();
    ImageVariantRegistry::all();
})->throwsNoExceptions();
