<?php

use App\Support\DownloadedFile;
use Illuminate\Http\Testing\FileFactory;
use Illuminate\Support\Facades\Config;

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

    expect($file->isFile)->toBeFalse()
        ->and($file->path)->toBe('invalid/path/to/file.txt')
        ->and($file->firstError)->toBe('Downloaded file is not a valid file.')
        ->and($file->dimensions)->toBeNull()
        ->and($file->size)->toBeNull()
        ->and($file->mimeType)->toBeNull()
        ->and($file->extension)->toBeNull()
        ->and($file->isValidImage)->toBeFalse();
});

it('returns true for a valid image file', function () {
    $image = imageFile();

    expect($image->isFile)->toBeTrue()
        ->and($image->firstError)->toBeNull()
        ->and($image->dimensions)->toBe([
            'width' => 10,
            'height' => 10,
        ])
        ->and($image->size)->toBeGreaterThan(0)
        ->and($image->mimeType)->toBe('image/jpeg')
        ->and($image->extension)->toBe('jpg')
        ->and($image->isValidImage)->toBeTrue();
});

it('validates image files with allowed extensions', function () {
    $extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

    foreach ($extensions as $ext) {
        $file = imageFile($ext);

        expect($file->isValidImage)->toBeTrue()
            ->and($file->firstError)->toBeNull();
    }
});

test('no errors are return on valid image', function () {
    $image = imageFile();

    expect($image->isValidImage)->toBeTrue()
        ->and($image->firstError)->toBeNull();
});

test('get error for invalid file', function () {
    $invalidFilePath = 'invalid/path/to/file.txt';
    $file = new DownloadedFile($invalidFilePath);

    expect($file->isValidImage)->toBeFalse()
        ->and($file->firstError)->toBe('Downloaded file is not a valid file.');
});

test('get error for invalid image', function () {
    $file = tempFile();

    expect($file->isValidImage)->toBeFalse()
        ->and($file->firstError)->toBe('Downloaded file is not a valid image.');
});

test('get error for invalid file image size', function () {
    Config::set('images.downloads.maxFileSize', 1);
    $image = imageFile();

    expect($image->isValidImage)->toBeFalse()
        ->and($image->firstError)->toBe('Downloaded file is too large.');
});

test('get error for invalid image type', function () {
    Config::set('images.downloads.allowedExtensions', ['png']);
    $image = imageFile('jpg');

    expect($image->isValidImage)->toBeFalse()
        ->and($image->firstError)->toBe('Downloaded file is not a valid image.');
});

it('returns false if the file size exceeds the maximum allowed', function () {
    Config::set('images.downloads.maxFileSize', 1);
    $image = imageFile();

    expect($image->isValidImage)->toBeFalse();
});

it('returns false for a file with an invalid extension', function () {
    $file = tempFile('file content');

    expect($file->isValidImage)->toBeFalse();
});

it('returns false for unsupported image file extensions', function () {
    Config::set('images.downloads.allowedExtensions', ['png']);
    $image = imageFile('jpg');

    expect($image->isValidImage)->toBeFalse();
});

it('returns false if the file is empty', function () {
    $file = tempFile();

    expect($file->isValidImage)->toBeFalse();
});

it('fails validation if the file has no extension or mime type', function () {
    $tempFile = tmpfile();
    $metaData = stream_get_meta_data($tempFile);
    $filePath = $metaData['uri'];

    $file = new DownloadedFile($filePath);

    expect($file->isValidImage)->toBeFalse();

    fclose($tempFile);
});

it('validates if file is file', function () {
    $file = tempFile('asd');
    expect($file->isFile)->toBeTrue();

    $file = imageFile('image.jpg');
    expect($file->isFile)->toBeTrue();
});

test('guessExtension method', function () {
    $emptyFile = tempFile();
    expect($emptyFile->extension)->toBeNull();

    $file = tempFile('file content');
    expect($file->extension)->toBe('txt');

    $image = imageFile();
    expect($image->extension)->toBe('jpg');

    $image2 = imageFile('png');
    expect($image2->extension)->toBe('png');

    $invalid = new DownloadedFile('invalid/path/to/file.txt');
    expect($invalid->extension)->toBeNull();
});

test('getMimeType method', function () {
    $emptyFile = tempFile();
    expect($emptyFile->mimeType)->toBe('application/x-empty');

    $file = tempFile('file content');
    expect($file->mimeType)->toBe('text/plain');

    $image = imageFile();
    expect($image->mimeType)->toBe('image/jpeg');

    $image2 = imageFile('png');
    expect($image2->mimeType)->toBe('image/png');

    $invalid = new DownloadedFile('invalid/path/to/file.txt');
    expect($invalid->mimeType)->toBeNull();
});

test('path', function () {
    $filePath = tempFile()->path;
    expect($filePath)->toBeString()
        ->and($filePath)->not->toBeEmpty();

    $image = imageFile();
    expect($image->path)->toBeString();

    $invalid = new DownloadedFile('invalid/path/to/file.txt');
    expect($invalid->path)->toBe('invalid/path/to/file.txt');
});

test('dimensions', function () {
    $file = tempFile();
    expect($file->dimensions)->toBeNull();

    $image = imageFile();
    $dimensions = $image->dimensions;
    expect($dimensions)->toBeArray()
        ->and($dimensions['width'])->toBe(10)
        ->and($dimensions['height'])->toBe(10);

    $invalid = new DownloadedFile('invalid/path/to/file.txt');
    expect($invalid->dimensions)->toBeNull();
});
