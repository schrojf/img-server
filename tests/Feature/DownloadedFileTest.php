<?php

use App\DownloadedFile;
use Illuminate\Http\Testing\FileFactory;

function imageFile(string $extension = 'jpg', $width = 10, $height = 10): DownloadedFile
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'test-');

    file_put_contents($tmpFile, (new FileFactory)->image("image.{$extension}", $width, $height)->getContent());

    return new DownloadedFile($tmpFile);
}

function tempFile(string $content = ''): DownloadedFile
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'test-');

    file_put_contents($tmpFile, $content);

    return new DownloadedFile($tmpFile);
}

test('invalid file path', function () {
    $invalidFilePath = 'invalid/path/to/file.txt';
    $file = new DownloadedFile($invalidFilePath);

    expect($file->isFile())->toBeFalse()
        ->and($file->path())->toBe('invalid/path/to/file.txt')
        ->and($file->dimensions())->toBeFalse()
        ->and($file->getSize())->toBeFalse()
        ->and($file->getMimeType())->toBeNull()
        ->and($file->guessExtension())->toBeNull()
        ->and($file->isValidImage())->toBeFalse();
});

it('returns true for a valid image file', function () {
    $image = imageFile();

    expect($image->isFile())->toBeTrue()
        ->and($image->dimensions())->toBe([
            10,
            10,
            2,
            'width="10" height="10"',
            'bits' => 8,
            'channels' => 3,
            'mime' => 'image/jpeg',
        ])
        ->and($image->getSize())->toBeGreaterThan(0)
        ->and($image->getMimeType())->toBe('image/jpeg')
        ->and($image->guessExtension())->toBe('jpg')
        ->and($image->isValidImage())->toBeTrue();
});

it('validates image files with allowed extensions', function () {
    $extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

    foreach ($extensions as $ext) {
        $file = imageFile($ext);

        expect($file->isValidImage())->toBeTrue();
    }
});

it('returns false if the file size exceeds the maximum allowed', function () {
    $image = imageFile();
    $image::$maxFileSize = 1;

    expect($image->isValidImage())->toBeFalse();
});

it('returns false for a file with an invalid extension', function () {
    $file = tempFile('file content');

    expect($file->isValidImage())->toBeFalse();
});

it('returns false for unsupported image file extensions', function () {
    $image = imageFile('jpg');
    $image::$allowedExtensions = ['png'];

    expect($image->isValidImage())->toBeFalse();
});

it('returns false if the file is empty', function () {
    $file = tempFile();

    expect($file->isValidImage())->toBeFalse();
});

it('fails validation if the file has no extension or mime type', function () {
    $tempFile = tmpfile();
    $metaData = stream_get_meta_data($tempFile);
    $filePath = $metaData['uri'];

    $file = new DownloadedFile($filePath);

    expect($file->isValidImage())->toBeFalse();

    fclose($tempFile);
});

it('validates if file is file', function () {
    $file = tempFile('asd');
    expect($file->isFile())->toBeTrue();

    $file = imageFile('image.jpg');
    expect($file->isFile())->toBeTrue();
});

test('guessExtension method', function () {
    $emptyFile = tempFile();
    expect($emptyFile->guessExtension())->toBeNull();

    $file = tempFile('file content');
    expect($file->guessExtension())->toBe('txt');

    $image = imageFile();
    expect($image->guessExtension())->toBe('jpg');

    $image2 = imageFile('png');
    expect($image2->guessExtension())->toBe('png');

    $invalid = new DownloadedFile('invalid/path/to/file.txt');
    expect($invalid->guessExtension())->toBeNull();
});

test('getMimeType method', function () {
    $emptyFile = tempFile();
    expect($emptyFile->getMimeType())->toBe('application/x-empty');

    $file = tempFile('file content');
    expect($file->getMimeType())->toBe('text/plain');

    $image = imageFile();
    expect($image->getMimeType())->toBe('image/jpeg');

    $image2 = imageFile('png');
    expect($image2->getMimeType())->toBe('image/png');

    $invalid = new DownloadedFile('invalid/path/to/file.txt');
    expect($invalid->getMimeType())->toBeNull();
});

test('path', function () {
    $filePath = tempFile()->path();
    expect($filePath)->toBeString()
        ->and($filePath)->not->toBeEmpty();

    $image = imageFile();
    expect($image->path())->toBeString();

    $invalid = new DownloadedFile('invalid/path/to/file.txt');
    expect($invalid->path())->toBe('invalid/path/to/file.txt');
});

test('dimensions', function () {
    $file = tempFile();
    expect($file->dimensions())->toBeFalse();

    $image = imageFile();
    $dimensions = $image->dimensions();
    expect($dimensions)->toBeArray()
        ->and($dimensions[0])->toBe(10)
        ->and($dimensions[1])->toBe(10);

    $invalid = new DownloadedFile('invalid/path/to/file.txt');
    expect($invalid->dimensions())->toBeFalse();
});
