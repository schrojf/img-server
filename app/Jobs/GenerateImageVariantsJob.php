<?php

namespace App\Jobs;

use App\Exceptions\ImageVariantGenerationException;
use App\Exceptions\InvalidImageStateException;
use App\Models\Enums\ImageStatus;
use App\Models\Image;
use App\Variants\GenerateVariantsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateImageVariantsJob implements ShouldQueue
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
    public function handle(GenerateVariantsAction $generateVariantsAction): void
    {
        if (is_null($image = $this->getImageModel())) {
            Log::warning("Image with id {$this->imageId} not found.");

            return;
        }

        Log::info("Starting GenerateImageVariantsJob for image ID {$this->imageId}");

        try {
            $generatedVariants = $generateVariantsAction->handle($image);

            DB::transaction(function () use ($generatedVariants) {
                $image = Image::lockForUpdate()->findOrFail($this->imageId);

                if ($image->status !== ImageStatus::GENERATING_VARIANTS) {
                    Log::error('Invalid image state transition attempted.', [
                        'image_id' => $this->imageId,
                        'current_status' => $image->status->value,
                        'expected_status' => ImageStatus::GENERATING_VARIANTS->value,
                    ]);

                    throw InvalidImageStateException::fromInvalidStateTransition($image->status, ImageStatus::GENERATING_VARIANTS);
                }

                $image->status = ImageStatus::DONE;
                $image->variant_files = $generatedVariants;
                $image->save();
            });
        } catch (ImageVariantGenerationException $exception) {
            Log::error($exception->getMessage(), $exception->getContext());

            DB::transaction(function () use ($exception) {
                $image = Image::lockForUpdate()->findOrFail($this->imageId);

                if ($image->status !== ImageStatus::GENERATING_VARIANTS) {
                    Log::error('Invalid image state transition attempted.', [
                        'image_id' => $this->imageId,
                        'current_status' => $image->status->value,
                        'expected_status' => ImageStatus::GENERATING_VARIANTS->value,
                    ]);

                    throw InvalidImageStateException::fromInvalidStateTransition($image->status, ImageStatus::GENERATING_VARIANTS);
                }

                $image->status = ImageStatus::FAILED;
                $image->last_error = $exception->getMessage();
                $image->save();
            });
        }
    }

    protected function getImageModel()
    {
        return DB::transaction(function () {
            if (is_null($image = Image::lockForUpdate()->find($this->imageId))) {
                return null;
            }

            if ($image->status !== ImageStatus::IMAGE_DOWNLOADED) {
                Log::error('Invalid image state transition attempted.', [
                    'image_id' => $this->imageId,
                    'current_status' => $image->status->value,
                    'expected_status' => ImageStatus::IMAGE_DOWNLOADED->value,
                ]);

                throw InvalidImageStateException::fromInvalidStateTransition($image->status, ImageStatus::IMAGE_DOWNLOADED);
            }

            if (! empty($image->variant_files)) {
                // Todo Log Error or throw some error
                return null;
            }

            $result = $image->update([
                'status' => ImageStatus::GENERATING_VARIANTS,
            ]);

            return $result ? $image : null;
        });
    }
}
