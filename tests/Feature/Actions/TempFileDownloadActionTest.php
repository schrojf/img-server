<?php

use App\Actions\TempFileDownloadAction;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

it('downloads a file and returns a temporary file path', function () {
    $url = 'https://example.org/file.txt';
    $content = 'File content';

    Http::fake([
        $url => Http::response($content, 200),
    ]);

    $tempFile = (new TempFileDownloadAction)->handle($url)->path();

    expect($tempFile)->toBeString()
        ->and($tempFile)->toStartWith(sys_get_temp_dir())
        ->and(file_exists($tempFile))->toBeTrue()
        ->and(file_get_contents($tempFile))->toBe($content);

    unlink($tempFile);

    Http::assertSent(function (Request $request) use ($url) {
        return $request->url() == $url &&
            $request->method() == 'GET' &&
            $request->header('User-Agent')[0] == 'ImageServer Downloader';
    });
});

it('throws an exception if connection fails', function () {
    $url = 'https://example.org/file.bin';

    Http::fake([
        $url => Http::response(null, 500) // Server error to simulate a failure
    ]);

    $downloader = new TempFileDownloadAction;

    expect(fn() => $downloader->handle($url))
        ->toThrow(RequestException::class);
});

it('respects the custom user agent', function () {
    $url = 'https://example.org/file.txt';

    TempFileDownloadAction::$userAgent = 'CustomAgent';

    Http::fake([
        $url => Http::response('File content'),
    ]);

    $tempFile = (new TempFileDownloadAction)->handle($url)->path();

    expect(file_exists($tempFile))->toBeTrue();

    unlink($tempFile);

    Http::assertSent(function (Request $request) {
        return $request->header('User-Agent')[0] === 'CustomAgent';
    });
});

it('uses no user agent when set to false', function () {
    $url = 'https://example.org/file.txt';

    TempFileDownloadAction::$userAgent = false;

    Http::fake([
        $url => Http::response('File content'),
    ]);

    $tempFile = (new TempFileDownloadAction)->handle($url)->path();

    expect(file_exists($tempFile))->toBeTrue();

    unlink($tempFile);

    Http::assertSent(function (Request $request) {
        return $request->header('User-Agent')[0] === "";
    });
});

it('uses a custom temporary file prefix', function () {
    $url = 'https://example.org/file.bin';

    TempFileDownloadAction::$tmpPrefix = 'custom-prefix';

    Http::fake([
        $url => Http::response('File content'),
    ]);

    $tempFile = (new TempFileDownloadAction)->handle($url)->path();

    expect(basename($tempFile))->toStartWith('custom-prefix');

    unlink($tempFile);
});

it('handles large file downloads', function () {
    $url = 'https://example.org/large-file.zip';
    $largeContent = str_repeat('a', 10 * 1024 * 1024); // 10 MB file

    Http::fake([
        $url => Http::response($largeContent, 200),
    ]);

    $tempFile = (new TempFileDownloadAction)->handle($url)->path();

    expect(filesize($tempFile))->toBe(strlen($largeContent));
})->skip();

it('cleans up temporary file on exception', function () {
    $url = 'https://example.org/file.txt';

    Http::fake([
        $url => Http::response(status: 500),
    ]);

    try {
        (new TempFileDownloadAction)->handle($url);
    } catch (\Illuminate\Http\Client\RequestException $e) {
        // Exception caught, now check if the temporary file was cleaned up
    }

    $tempFiles = glob(sys_get_temp_dir() . '/image-server*');
    expect($tempFiles)->toBeEmpty();
})->skip();

it('respects maximum execution time', function () {
    $url = 'https://example.org/slow-download.txt';
    $content = 'Slow download content';

    $maxExecutionTime = ini_get('max_execution_time');
    if ($maxExecutionTime == 0) {
        $this->markTestSkipped('max_execution_time is set to unlimited');
    }

    Http::fake([
        $url => function () use ($content, $maxExecutionTime) {
            sleep($maxExecutionTime + 1);
            return Http::response($content, 200);
        },
    ]);

    (new TempFileDownloadAction)->handle($url);
})->throws(ConnectionException::class)->skip();
