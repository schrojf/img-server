<?php

use App\Actions\DownloadImageAction;
use App\Actions\GenerateRandomHashFileNameAction;
use App\Actions\TempFileDownloadAction;
use App\Exceptions\DownloadImageActionException;
use App\Exceptions\InvalidImageValueException;
use App\Jobs\DownloadImageJob;
use App\Jobs\GenerateImageVariantsJob;
use App\Models\Enums\ImageStatus;
use App\Support\ImageFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

test('log was called when no image was found', function () {
    $job = new DownloadImageJob(1234);

    Log::spy();

    $job->handle(new DownloadImageAction(
        mock(TempFileDownloadAction::class)->makePartial(),
        mock(GenerateRandomHashFileNameAction::class)->makePartial()
    ));

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Image with id 1234 not found.');
});

test('action with image model was called', function () {
    $image = image();
    $image->update(['status' => ImageStatus::QUEUED]);

    $job = new DownloadImageJob($image->id);

    $m = mock(DownloadImageAction::class)
        ->shouldReceive('handle')
        ->once()
        ->withArgs(function (int $imageId) use ($image) {
            return $imageId === $image->id;
        })
        ->andReturn(new ImageFile('disk', 'image.jpg', 'image/jpeg', 0, 0, 0))
        ->getMock();

    Queue::fake([
        GenerateImageVariantsJob::class,
    ]);

    $job->handle($m);

    $image->refresh();

    expect($image->last_error)->toBeNull()
        ->and($image->status)->toBe(ImageStatus::QUEUED);

    Queue::assertPushed(GenerateImageVariantsJob::class, function ($job) use ($image) {
        return $job->imageId === $image->id;
    });
});

test('invalid state transition', function () {
    $image = image();
    $job = new DownloadImageJob($image->id);

    $errors = [
        DownloadImageActionException::class => 'error',
        InvalidImageValueException::class => 'critical',
        // InvalidImageStateException::class => 'critical',, // Require extra set-up
    ];

    foreach ($errors as $error => $method) {
        $actionMock = mock(DownloadImageAction::class)
            ->shouldReceive('handle')
            ->once()
            ->with($image->id)
            ->andThrow(new $error("Test error: {$error}", context: [
                'image_id' => $image->id,
                'error' => $error,
            ]))
            ->getMock();

        Log::spy();

        $job->handle($actionMock);

        Log::shouldHaveReceived($method)
            ->once()
            ->with("Test error: {$error}", [
                'image_id' => $image->id,
                'error' => $error,
            ]);
    }
});
