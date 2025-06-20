<?php

use App\Actions\MarkExpiredImagesAction;
use App\Models\Enums\ImageStatus;
use App\Models\Image;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

it('marks expired processing images as expired', function () {
    // Arrange
    $thresholdHours = 24;
    $batchCount = 50;
    $now = Carbon::now();

    // Create images that should expire
    Collection::times(5, function () use ($now, $thresholdHours) {
        tap(image(), function (Image $image) use ($now, $thresholdHours) {
            $image->status = ImageStatus::PROCESSING;
            $image->updated_at = $now->copy()->subHours($thresholdHours + 1);
            $image->save();
        });
    });

    // Create images that are still fresh
    Collection::times(3, function () use ($now, $thresholdHours) {
        tap(image(), function (Image $image) use ($now, $thresholdHours) {
            $image->status = ImageStatus::PROCESSING;
            $image->updated_at = $now->copy()->subHours($thresholdHours - 1);
            $image->save();
        });
    });

    // Create images with different statuses
    Collection::times(2, function () use ($now, $thresholdHours) {
        tap(image(), function (Image $image) use ($now, $thresholdHours) {
            $image->status = ImageStatus::DONE;
            $image->updated_at = $now->copy()->subHours($thresholdHours + 2);
            $image->save();
        });
    });

    // Act
    $action = new MarkExpiredImagesAction(afterHours: $thresholdHours, batchCount: $batchCount);
    $processed = $action->handle();

    // Assert
    expect($processed)->toBe(5);

    $expired = Image::where('status', ImageStatus::EXPIRED)->count();
    $processing = Image::where('status', ImageStatus::PROCESSING)->count();
    $completed = Image::where('status', ImageStatus::DONE)->count();

    expect($expired)->toBe(5);
    expect($processing)->toBe(3);
    expect($completed)->toBe(2);
});
