<?php

use App\Actions\GenerateVariantsAction;
use App\Jobs\GenerateImageVariantsJob;
use App\Models\Enums\ImageStatus;
use App\Support\ImageFile;
use Illuminate\Support\Facades\Log;

test('log was called when no image was found', function () {
    $job = new GenerateImageVariantsJob(1234);

    Log::spy();

    $job->handle(mock(GenerateVariantsAction::class)->makePartial());

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Image with id 1234 not found.');
});

test('action with image model was called', function () {
    $image = image();
    $image->update(['status' => ImageStatus::IMAGE_DOWNLOADED]);

    $job = new GenerateImageVariantsJob($image->id);

    $m = mock(GenerateVariantsAction::class)
        ->shouldReceive('handle')
        ->once()
        ->withArgs(function (int $imageId) use ($image) {
            return $imageId === $image->id;
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
        ->and($image->status)->toBe(ImageStatus::IMAGE_DOWNLOADED);
});
