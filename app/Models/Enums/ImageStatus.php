<?php

namespace App\Models\Enums;

enum ImageStatus: string
{
    case QUEUED = 'queued';

    case DOWNLOADING_IMAGE = 'downloading_image';

    case IMAGE_DOWNLOADED = 'image_downloaded';

    case GENERATING_VARIANTS = 'generating_variants';

    case DONE = 'done';

    case FAILED = 'failed';

    case EXPIRED = 'expired';

    public function isProcessing(): bool
    {
        return in_array($this, [self::QUEUED, self::DOWNLOADING_IMAGE, self::IMAGE_DOWNLOADED, self::GENERATING_VARIANTS]);
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::DONE, self::FAILED, self::EXPIRED => true,
            default => false,
        };
    }
}
