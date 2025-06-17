<?php

namespace App\Actions;

use App\Data\DownloadConfig;
use App\Support\DownloadedFile;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TempFileDownloadAction
{
    protected ?DownloadConfig $config;

    public function __construct(?DownloadConfig $config = null)
    {
        $this->config = $config ?? DownloadConfig::fromConfig();
    }

    public function handle(string $url): DownloadedFile
    {
        $attempt = 0;

        while ($attempt < $this->config->retries) {
            $attempt++;

            $temporaryFile = tempnam(sys_get_temp_dir(), $this->config->tmpPrefix);
            $stream = fopen($temporaryFile, 'w+');

            if (! $stream) {
                throw new RuntimeException('Unable to open temp file for writing.');
            }

            try {
                $downloaded = 0;

                $response = Http::withUserAgent($this->config->userAgent)
                    ->timeout($this->config->timeout)
                    ->withOptions([
                        'stream' => true,
                        'read_timeout' => $this->config->timeout,
                        'connect_timeout' => 10,
                    ])
                    ->throw()
                    ->retry(0)
                    ->get($url);

                // Early size check
                $contentLength = $response->header('Content-Length');
                if ($contentLength !== null && (int) $contentLength > $this->config->maxFileSize) {
                    throw new RuntimeException("File size exceeds limit of maximum allowed {$this->config->maxFileSize} bytes.");
                }

                $bodyStream = $response->toPsrResponse()->getBody();

                while (! $bodyStream->eof()) {
                    $chunk = $bodyStream->read(1024 * 1024);
                    $downloaded += strlen($chunk);

                    if ($downloaded > $this->config->maxFileSize) {
                        throw new RuntimeException("File size exceeds limit of maximum allowed {$this->config->maxFileSize} bytes.");
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

                if ($attempt >= $this->config->retries) {
                    throw $e;
                }

                $delay = ($this->config->baseBackoffMs * (2 ** ($attempt - 1))) + rand(0, 100);
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
