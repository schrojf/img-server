<?php

use App\Models\Image;

test('image model was created', function () {
    $image = image();

    expect($image)->toBeInstanceOf(Image::class);
});
