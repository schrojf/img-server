<?php

namespace App\Jobs;

use App\Actions\DownloadImageAction;
use App\Exceptions\DownloadImageActionException;
use App\Models\Image;
use Illuminate\Contracts\Queue\ShouldQueue;
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
        if (is_null($image = Image::find($this->imageId))) {
            Log::warning("Image with id {$this->imageId} not found.");

            return;
        }

        try {
            $imageFile = $downloadImageAction->handle($image);
        } catch (DownloadImageActionException $exception) {
            $image->last_error = $exception->getMessage();
            $image->save();

            Log::error($exception->getMessage(), $exception->context());
        }
    }
}
