<?php

use App\Actions\DownloadImageAction;
use App\Exceptions\DownloadImageActionException;
use App\Exceptions\InvalidImageStateException;
use App\Jobs\DownloadImageJob;
use App\Jobs\GenerateImageVariantsJob;
use App\Models\Enums\ImageStatus;
use App\Models\Image;
use App\Support\ImageFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

test('log was called when no image was found', function () {
    $job = new DownloadImageJob(1234);

    Log::spy();

    $job->handle(mock(DownloadImageAction::class)->makePartial());

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Image with id 1234 not found.');
})->todo('Change test to reflect changes made DownloadImageJob class.');

test('action with image model was called', function () {
    $image = image();
    $image->update(['status' => ImageStatus::QUEUED]);

    $job = new DownloadImageJob($image->id);

    $m = mock(DownloadImageAction::class)
        ->shouldReceive('handle')
        ->once()
        ->withArgs(function (Image $imageArg) use ($image) {
            return $imageArg->id === $image->id;
        })
        ->andReturn(new ImageFile('disk', 'image.jpg', 'image/jpeg', 0, 0, 0))
        ->getMock();

    Queue::fake([
        GenerateImageVariantsJob::class,
    ]);

    $job->handle($m);

    $image->refresh();

    expect($image->last_error)->toBeNull()
        ->and($image->status)->toBe(ImageStatus::IMAGE_DOWNLOADED);

    Queue::assertPushed(GenerateImageVariantsJob::class, function ($job) use ($image) {
        return $job->imageId === $image->id;
    });
})->todo('Change test to reflect changes made DownloadImageJob class.');

test('exception will be caught and save last error', function () {
    $image = image();
    $image->update(['status' => ImageStatus::QUEUED]);

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

    $image->refresh();

    expect($image->last_error)->toBe('Test error message.')
        ->and($image->status)->toBe(ImageStatus::FAILED);
})->todo('Change test to reflect changes made DownloadImageJob class.');

test('invalid state transition', function () {
    $image = image();
    $job = new DownloadImageJob($image->id);
    $actionMock = mock(DownloadImageAction::class)->makePartial();

    Log::spy();

    expect(fn () => $job->handle($actionMock))->toThrow(
        InvalidImageStateException::class,
        "Invalid image state: expected 'queued', got 'expired'",
    );

    Log::shouldHaveReceived('error')
        ->once()
        ->with('Invalid image state transition attempted.', [
            'image_id' => $image->id,
            'current_status' => ImageStatus::EXPIRED->value,
            'expected_status' => ImageStatus::QUEUED->value,
        ]);
})->todo('Change test to reflect changes made DownloadImageJob class.');
