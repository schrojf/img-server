<?php

namespace App\Jobs;

use App\Actions\DownloadImageAction;
use App\Exceptions\DownloadImageActionException;
use App\Exceptions\InvalidImageStateException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
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
        Log::info("Starting DownloadImageJob for image ID {$this->imageId}");

        try {
            $downloadImageAction->handle($this->imageId);
            dispatch(new GenerateImageVariantsJob($this->imageId));
        } catch (DownloadImageActionException $exception) {
            Log::error($exception->getMessage(), $exception->context());
            report($exception);
        } catch (InvalidImageStateException $exception) {
            Log::error($exception->getMessage(), $exception->context());
            report($exception);
        } catch (ModelNotFoundException $exception) {
            Log::warning("Image with id {$this->imageId} not found.");
        }
    }
}
