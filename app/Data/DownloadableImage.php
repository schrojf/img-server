<?php

namespace App\Data;

readonly class DownloadableImage
{
    public function __construct(
        public int $id,
        public string $url,
    ) {}
}
