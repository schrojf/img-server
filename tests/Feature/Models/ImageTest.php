<?php

use App\Models\Image;

test('image model was created', function () {
    $image = Image::create([]);

    expect($image)->toBeInstanceOf(Image::class);
});
