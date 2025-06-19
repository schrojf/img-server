<?php

namespace App\Models\Enums;

enum ImageStatus: string
{
    case QUEUED = 'queued';

    case PROCESSING = 'processing';

    case DONE = 'done';

    case FAILED = 'failed';

    case EXPIRED = 'expired';

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
