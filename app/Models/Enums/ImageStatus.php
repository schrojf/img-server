<?php

namespace App\Models\Enums;

enum ImageStatus: string
{
    case QUEUED = 'queued';

    case PROCESSING = 'processing';
    case DOWNLOADING_IMAGE = 'downloading_image';

    case IMAGE_DOWNLOADED = 'image_downloaded';

    case DONE = 'done';

    case FAILED = 'failed';

    case EXPIRED = 'expired';

    // case DOWNLOAD_QUEUED = 'download_queued';

    // case DOWNLOAD_PENDING = 'download_pending';

    // case DOWNLOAD_FAILED = 'download_failed';

    public function isProcessing(): bool
    {
        return in_array($this, [self::QUEUED, self::PROCESSING]);
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::DONE, self::FAILED, self::EXPIRED => true,
            default => false,
        };
    }
}
