<?php

use App\Actions\DownloadImageAction;
use App\Actions\GenerateRandomHashFileNameAction;
use App\Actions\TempFileDownloadAction;
use App\Exceptions\DownloadImageActionException;
use App\Models\Image;
use App\Support\DownloadedFile;
use App\Support\ImageFile;
use App\Support\ImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function fakeImageUrl(): string
{
    $imageFile = UploadedFile::fake()->image('image.jpg');
    $url = 'https://example.org/image.jpg';
    Http::fake([
        $url => Http::response($imageFile->getContent()),
    ]);

    return $url;
}

function fakeTextUrl(): string
{
    $url = 'https://example.org/file.txt';
    Http::fake([
        $url => Http::response('Content goes here.'),
    ]);

    return $url;
}

function fakeErrorUrl(): string
{
    $url = 'https://example.org/image.jpg';
    Http::fake([
        $url => Http::response('', 500),
    ]);

    return $url;
}

test('downloads and saves image from valid URL into storage', function () {
    $url = fakeImageUrl();
    $imageModel = image($url);
    Storage::fake(ImageStorage::original());

    $downloadImageAction = new DownloadImageAction(new TempFileDownloadAction, new GenerateRandomHashFileNameAction);

    $file = $downloadImageAction->handle($imageModel);

    $imageModel->refresh();

    expect($imageModel->image_file)->toMatchArray($file->toArray())
        ->and($imageModel->last_error)->toBeNull();

    Storage::disk(ImageStorage::original())->assertExists($file->fileName);
});

test('throws if image model is not persisted', function () {
    $action = new DownloadImageAction(new TempFileDownloadAction, new GenerateRandomHashFileNameAction);
    $action->handle(new Image);
})->throws(
    DownloadImageActionException::class,
    'Image model is not persisted in the database.',
);

test('throws if image model already has an image_file', function () {
    $imageModel = image();
    $imageModel->update(['image_file' => new ImageFile(
        'disk',
        'image.jpg',
        'image/jpeg',
        0,
        0,
        0,
    )]);

    $action = new DownloadImageAction(new TempFileDownloadAction, new GenerateRandomHashFileNameAction);

    expect(fn () => $action->handle($imageModel))->toThrow(
        DownloadImageActionException::class,
        "Image [ID: {$imageModel->id}] already has an image_file assigned."
    );
});

test('throws if remote image URL returns server error', function () {
    $url = fakeErrorUrl();
    $imageModel = image($url);

    $action = new DownloadImageAction(new TempFileDownloadAction, new GenerateRandomHashFileNameAction);

    expect(fn () => $action->handle($imageModel))->toThrow(
        DownloadImageActionException::class,
        "Failed to download image from URL [{$imageModel->original_url}]: ",
    );
});

test('throws if downloaded file is not a valid image', function () {
    $url = fakeTextUrl();
    $imageModel = image($url);

    $action = new DownloadImageAction(new TempFileDownloadAction, new GenerateRandomHashFileNameAction);

    expect(fn () => $action->handle($imageModel))->toThrow(
        DownloadImageActionException::class,
        "Downloaded file is not a valid image for image [ID: {$imageModel->id}]. Reason: Downloaded file is not a valid image.",
    );
});

test('throws if generated file name already exists in storage', function () {
    $url = fakeImageUrl();
    $imageModel = image($url);
    $filesystem = Storage::fake(ImageStorage::original());

    $nameActionMock = mock(GenerateRandomHashFileNameAction::class)
        ->shouldReceive('handle')
        ->once()
        ->withNoArgs()
        ->andReturn('saved/original_filename')
        ->getMock();

    $downloadImageAction = new DownloadImageAction(new TempFileDownloadAction, $nameActionMock);

    $filesystem->put("saved/original_filename_{$imageModel->id}.jpg", 'content');

    expect(fn () => $downloadImageAction->handle($imageModel))->toThrow(
        DownloadImageActionException::class,
        "File collision: Generated file name 'saved/original_filename_{$imageModel->id}.jpg' already exists on disk 'downloaded'.",
    );
});

test('saves image using mocked file and filename actions', function () {
    $url = 'https://example.org/image.jpg';
    $imageModel = image($url);
    $filesystem = Storage::fake(ImageStorage::original());

    $filesystem->put($tmpFilePath = 'tmp/path/to/image.jpg', UploadedFile::fake()->image('image.jpg')->getContent());

    $downloadFileActionMock = mock(TempFileDownloadAction::class)
        ->shouldReceive('handle')
        ->once()
        ->with($url)
        ->andReturn(new DownloadedFile($filesystem->path($tmpFilePath)))
        ->getMock();

    $nameActionMock = mock(GenerateRandomHashFileNameAction::class)
        ->shouldReceive('handle')
        ->once()
        ->withNoArgs()
        ->andReturn('saved/original_filename')
        ->getMock();

    $downloadImageAction = new DownloadImageAction($downloadFileActionMock, $nameActionMock);

    $fileSize = $filesystem->size($tmpFilePath);

    $file = $downloadImageAction->handle($imageModel);

    $imageModel->refresh();

    expect($imageModel->image_file)->toMatchArray([
        'disk' => ImageStorage::original(),
        'file_name' => "saved/original_filename_{$imageModel->id}.jpg",
        'mime_type' => 'image/jpeg',
        'size' => $fileSize,
    ])
        ->and($imageModel->last_error)->toBeNull();

    Storage::disk(ImageStorage::original())->assertExists($file->fileName);
});

test('deletes temporary file after saving image', function () {
    $url = 'https://example.org/image.jpg';
    $imageModel = image($url);
    Storage::fake(ImageStorage::original());

    // Create a real temporary file
    $tmpPath = tempnam(sys_get_temp_dir(), 'test-img');
    file_put_contents($tmpPath, UploadedFile::fake()->image('image.jpg')->getContent());

    // Assert temp file exists
    expect(file_exists($tmpPath))->toBeTrue();

    $downloadFileActionMock = mock(TempFileDownloadAction::class)
        ->shouldReceive('handle')
        ->once()
        ->with($url)
        ->andReturn(new DownloadedFile($tmpPath))
        ->getMock();

    $fileName = 'custom_random_name';
    $nameActionMock = mock(GenerateRandomHashFileNameAction::class)
        ->shouldReceive('handle')
        ->once()
        ->withNoArgs()
        ->andReturn($fileName)
        ->getMock();

    $downloadImageAction = new DownloadImageAction($downloadFileActionMock, $nameActionMock);

    $file = $downloadImageAction->handle($imageModel);

    expect(file_exists($tmpPath))->toBeFalse();
});
