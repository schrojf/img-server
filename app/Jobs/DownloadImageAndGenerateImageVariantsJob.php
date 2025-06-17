<?php

namespace App\Jobs;

use App\Actions\DownloadImageAction;
use App\Exceptions\DownloadImageActionException;
use App\Exceptions\ImageVariantGenerationException;
use App\Exceptions\InvalidImageStateException;
use App\Variants\GenerateVariantsAction;
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
        } catch (DownloadImageActionException $exception) {
            Log::error($exception->getMessage(), $exception->context());
            report($exception);
        } catch (ImageVariantGenerationException $exception) {
            Log::error($exception->getMessage(), $exception->getContext());
            report($exception);
        } catch (InvalidImageStateException $exception) {
            Log::error($exception->getMessage(), $exception->context());
            report($exception);
        } catch (ModelNotFoundException $exception) {
            Log::warning("Image with id {$this->imageId} not found.");
        }
    }
}
