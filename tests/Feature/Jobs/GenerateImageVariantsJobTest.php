<?php

use App\Exceptions\ImageVariantGenerationException;
use App\Exceptions\InvalidImageStateException;
use App\Jobs\GenerateImageVariantsJob;
use App\Models\Enums\ImageStatus;
use App\Models\Image;
use App\Support\ImageFile;
use App\Variants\GenerateVariantsAction;
use Illuminate\Support\Facades\Log;

test('log was called when no image was found', function () {
    $job = new GenerateImageVariantsJob(1234);

    Log::spy();

    $job->handle(mock(GenerateVariantsAction::class)->makePartial());

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Image with id 1234 not found.');
});

test('invalid state detected', function () {
    $image = image();
    $job = new GenerateImageVariantsJob($image->id);
    $actionMock = mock(GenerateVariantsAction::class)->makePartial();

    Log::spy();

    expect(fn () => $job->handle($actionMock))->toThrow(
        InvalidImageStateException::class,
        "Invalid image state: expected 'image_downloaded', got 'expired'",
    );

    Log::shouldHaveReceived('error')
        ->once()
        ->with('Invalid image state transition attempted.', [
            'image_id' => $image->id,
            'current_status' => ImageStatus::EXPIRED->value,
            'expected_status' => ImageStatus::IMAGE_DOWNLOADED->value,
        ]);
});

test('action with image model was called', function () {
    $image = image();
    $image->update(['status' => ImageStatus::IMAGE_DOWNLOADED]);

    $job = new GenerateImageVariantsJob($image->id);

    $m = mock(GenerateVariantsAction::class)
        ->shouldReceive('handle')
        ->once()
        ->withArgs(function (Image $imageArg) use ($image) {
            return $imageArg->id === $image->id;
        })
        ->andReturn([
            'test-variant-name' => [
                'jpg' => new ImageFile('disk', 'image.jpg', 'image/jpeg', 0, 0, 0),
            ],
        ])
        ->getMock();

    $job->handle($m);

    $image->refresh();

    expect($image->last_error)->toBeNull()
        ->and($image->status)->toBe(ImageStatus::DONE);
});

test('exception will be caught and save last error', function () {
    $image = image();
    $image->update(['status' => ImageStatus::IMAGE_DOWNLOADED]);

    $job = new GenerateImageVariantsJob($image->id);

    $m = mock(GenerateVariantsAction::class)
        ->shouldReceive('handle')
        ->once()
        ->withArgs(function (Image $imageArg) use ($image) {
            return $imageArg->id === $image->id;
        })
        ->andThrow(ImageVariantGenerationException::encodingError(
            'jpeg',
            'test-variant-name'
        ))
        ->getMock();

    $job->handle($m);

    $image->refresh();

    expect($image->last_error)->toBe("Failed to encode image variant 'test-variant-name' to format 'jpeg'")
        ->and($image->status)->toBe(ImageStatus::FAILED);
});
