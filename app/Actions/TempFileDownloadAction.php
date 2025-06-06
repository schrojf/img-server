<?php

namespace App\Actions;

use App\DownloadedFile;
use Illuminate\Support\Facades\Http;

class TempFileDownloadAction
{
    public static string $tmpPrefix = 'image-server-';

    public static string|false $userAgent = 'ImageServer Downloader';

    /**
     * @param string $url
     * @return string
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function handle(string $url): DownloadedFile
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), static::$tmpPrefix);

        Http::withUserAgent(static::$userAgent)
            ->throw()
            ->sink($temporaryFile)
            ->get($url);

        return new DownloadedFile($temporaryFile);
    }
}
