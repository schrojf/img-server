<?php

namespace App\Jobs;

use App\Actions\DownloadImageAction;
use App\Actions\GenerateVariantsAction;
use App\Exceptions\DownloadImageActionException;
use App\Exceptions\ImageVariantGenerationException;
use App\Exceptions\InvalidImageStateException;
use App\Exceptions\InvalidImageValueException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DownloadImageAndGenerateImageVariantsJob
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
    public function handle(
        DownloadImageAction $downloadImageAction,
        GenerateVariantsAction $generateVariantsAction,
    ): void {
        Log::info("Starting 'DownloadImageAndGenerateImageVariantsJob' for image ID {$this->imageId}");

        try {
            $downloadImageAction->handle($this->imageId);
            $generateVariantsAction->handle($this->imageId);
        } catch (InvalidImageValueException|InvalidImageStateException $exception) {
            Log::critical($exception->getMessage(), $exception->getContext());
        } catch (DownloadImageActionException|ImageVariantGenerationException $exception) {
            Log::error($exception->getMessage(), $exception->getContext());
        } catch (ModelNotFoundException $exception) {
            Log::warning("Image with id {$this->imageId} not found.");
        }
    }
}
