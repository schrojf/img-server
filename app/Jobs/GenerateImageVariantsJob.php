<?php

namespace App\Jobs;

use App\Actions\GenerateVariantsAction;
use App\Exceptions\ImageVariantGenerationException;
use App\Exceptions\InvalidImageStateException;
use App\Exceptions\InvalidImageValueException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
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
        Log::info("Starting GenerateImageVariantsJob for image ID {$this->imageId}");

        try {
            $generateVariantsAction->handle($this->imageId);
        } catch (InvalidImageValueException|InvalidImageStateException $exception) {
            Log::critical($exception->getMessage(), $exception->getContext());
        } catch (ImageVariantGenerationException $exception) {
            Log::error($exception->getMessage(), $exception->getContext());
        } catch (ModelNotFoundException $exception) {
            Log::warning("Image with id {$this->imageId} not found.");
        }
    }
}
