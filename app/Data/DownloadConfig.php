<?php

namespace App\Data;

readonly class DownloadConfig
{
    public function __construct(
        public int $maxFileSize,
        public int $timeout,
        public int $retries,
        public int $baseBackoffMs,
        public string $userAgent,
        public string $tmpPrefix,
    ) {}

    public static function fromConfig(): static
    {
        $cfg = config('image.downloads');

        return new static(
            maxFileSize: $cfg['maxFileSize'] ?? 30 * 1024 * 1024,
            timeout: $cfg['timeout'] ?? 120,
            retries: $cfg['retries'] ?? 3,
            baseBackoffMs: $cfg['baseBackoffMs'] ?? 200,
            userAgent: $cfg['userAgent'] ?? 'ImageServer Downloader',
            tmpPrefix: $cfg['tmpPrefix'] ?? 'image-server-',
        );
    }
}
