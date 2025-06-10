<?php

use App\Actions\DownloadImageAction;
use App\Exceptions\DownloadImageActionException;
use App\Jobs\DownloadImageJob;
use App\Models\Image;
use App\Support\ImageFile;
use Illuminate\Support\Facades\Log;

test('log was called when no image was found', function () {
    $job = new DownloadImageJob(1234);

    Log::spy();

    $job->handle(mock(DownloadImageAction::class)->makePartial());

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Image with id 1234 not found.');
});

test('action with image model was called', function () {
    $image = image();
    $job = new DownloadImageJob($image->id);

    $m = mock(DownloadImageAction::class)
        ->shouldReceive('handle')
        ->once()
        ->withArgs(function (Image $imageArg) use ($image) {
            return $imageArg->id === $image->id;
        })
        ->andReturn(new ImageFile('disk', 'image.jpg', 'image/jpeg', 0, 0, 0))
        ->getMock();

    $job->handle($m);
});

test('exception will be caught and save last error', function () {
    $image = image();
    $job = new DownloadImageJob($image->id);

    $m = mock(DownloadImageAction::class)
        ->shouldReceive('handle')
        ->once()
        ->withArgs(function (Image $imageArg) use ($image) {
            return $imageArg->id === $image->id;
        })
        ->andThrow(DownloadImageActionException::make(
            'Test error message.',
        ))
        ->getMock();

    $job->handle($m);

    expect($image->fresh()->last_error)->toBe('Test error message.');
});
