<?php

namespace App\Variants\Modifiers;

use Intervention\Image\Image;

abstract class ImageModifier
{
    abstract public function apply(Image $image): Image;
}
