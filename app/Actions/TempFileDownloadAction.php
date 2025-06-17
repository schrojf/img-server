<?php

namespace App\Actions;

use App\Support\DownloadedFile;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TempFileDownloadAction
{
    protected const MAX_FILE_SIZE = 30 * 1024 * 1024; // 30 MB

    protected const TIMEOUT = 120; // seconds

    protected const MAX_RETRIES = 3;

    protected const BASE_BACKOFF_MS = 200; // milliseconds

    public static string $tmpPrefix = 'image-server-';

    public static string|false $userAgent = 'ImageServer Downloader';

    /**
     * @return string
     *
     * @throws \Illuminate\Http\Client\RequestException|\RuntimeException
     */
    public function handle(string $url): DownloadedFile
    {
        $attempt = 0;

        while ($attempt < static::MAX_RETRIES) {
            $attempt++;

            $temporaryFile = tempnam(sys_get_temp_dir(), static::$tmpPrefix);
            $stream = fopen($temporaryFile, 'w+');

            if (! $stream) {
                throw new RuntimeException('Unable to open temp file for writing.');
            }

            try {
                $downloaded = 0;

                $response = Http::withUserAgent(static::$userAgent)
                    ->timeout(static::TIMEOUT)
                    ->withOptions([
                        'stream' => true,
                        'read_timeout' => static::TIMEOUT,
                        'connect_timeout' => 10,
                    ])
                    ->throw()
                    ->retry(0)
                    ->get($url);

                // Early size check
                $contentLength = $response->header('Content-Length');
                if ($contentLength !== null && (int) $contentLength > static::MAX_FILE_SIZE) {
                    throw new RuntimeException('File size exceeds 30MB');
                }

                $bodyStream = $response->toPsrResponse()->getBody();

                while (! $bodyStream->eof()) {
                    $chunk = $bodyStream->read(1024 * 1024);
                    $downloaded += strlen($chunk);

                    if ($downloaded > static::MAX_FILE_SIZE) {
                        throw new RuntimeException('File size exceeds 30MB');
                    }

                    if (fwrite($stream, $chunk) === false) {
                        throw new RuntimeException('Failed to write to temp file.');
                    }
                }

                fclose($stream);

                return new DownloadedFile($temporaryFile);

            } catch (RequestException $e) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
                @unlink($temporaryFile);

                if ($attempt >= static::MAX_RETRIES) {
                    throw $e;
                }

                $delay = (static::BASE_BACKOFF_MS * (2 ** ($attempt - 1))) + rand(0, 100);
                usleep($delay * 1000);

                continue;

            } catch (\Throwable $e) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
                @unlink($temporaryFile);

                throw $e;
            }
        }

        throw new RuntimeException("Failed to download file after {$attempt} retries.");
    }
}
