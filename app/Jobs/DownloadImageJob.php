<?php

namespace App\Jobs;

use App\Actions\DownloadImageAction;
use App\Exceptions\DownloadImageActionException;
use App\Exceptions\InvalidImageStateException;
use App\Models\Enums\ImageStatus;
use App\Models\Image;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DownloadImageJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $imageId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DownloadImageAction $downloadImageAction): void
    {
        $image = DB::transaction(function () {
            if (is_null($image = Image::lockForUpdate()->find($this->imageId))) {
                return null;
            }

            if ($image->status !== ImageStatus::QUEUED) {
                Log::error('Invalid image state transition attempted.', [
                    'image_id' => $this->imageId,
                    'current_status' => $image->status->value,
                    'expected_status' => ImageStatus::QUEUED->value,
                ]);

                throw InvalidImageStateException::fromInvalidStateTransition($image->status, ImageStatus::QUEUED);
            }

            $result = $image->update([
                'status' => ImageStatus::DOWNLOADING_IMAGE,
            ]);

            return $result ? $image : null;
        });

        if (is_null($image)) {
            Log::warning("Image with id {$this->imageId} not found.");

            return;
        }

        Log::info("Starting DownloadImageJob for image ID {$this->imageId}");

        try {
            $imageFile = $downloadImageAction->handle($image);

            DB::transaction(function () use ($imageFile) {
                $image = Image::lockForUpdate()->findOrFail($this->imageId);

                if ($image->status !== ImageStatus::DOWNLOADING_IMAGE) {
                    Log::error('Invalid image state transition attempted.', [
                        'image_id' => $this->imageId,
                        'current_status' => $image->status->value,
                        'expected_status' => ImageStatus::DOWNLOADING_IMAGE->value,
                    ]);

                    throw InvalidImageStateException::fromInvalidStateTransition($image->status, ImageStatus::DOWNLOADING_IMAGE);
                }

                $image->image_file = $imageFile->toArray();
                $image->status = ImageStatus::IMAGE_DOWNLOADED;
                $image->save();
            });

            dispatch(new GenerateImageVariantsJob($image->id));
        } catch (DownloadImageActionException $exception) {
            Log::error($exception->getMessage(), $exception->context());

            DB::transaction(function () use ($exception) {
                $image = Image::lockForUpdate()->findOrFail($this->imageId);

                if ($image->status !== ImageStatus::DOWNLOADING_IMAGE) {
                    Log::error('Invalid image state transition attempted.', [
                        'image_id' => $this->imageId,
                        'current_status' => $image->status->value,
                        'expected_status' => ImageStatus::DOWNLOADING_IMAGE->value,
                    ]);

                    throw InvalidImageStateException::fromInvalidStateTransition($image->status, ImageStatus::DOWNLOADING_IMAGE);
                }

                $image->status = ImageStatus::FAILED;
                $image->last_error = $exception->getMessage();
                $image->save();
            });
        }
    }
}
