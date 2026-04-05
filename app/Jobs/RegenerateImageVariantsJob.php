<?php

namespace App\Jobs;

use App\Actions\GenerateVariantsAction;
use App\Exceptions\ImageVariantGenerationException;
use App\Exceptions\InvalidImageStateException;
use App\Exceptions\InvalidImageValueException;
use App\Models\Enums\ImageStatus;
use App\Models\Image;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RegenerateImageVariantsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $imageId,
    ) {}

    public function handle(GenerateVariantsAction $generateVariantsAction): void
    {
        Log::info("Starting RegenerateImageVariantsJob for image ID {$this->imageId}");

        try {
            $this->deleteVariantFilesAndResetState();
            $generateVariantsAction->handle($this->imageId);
        } catch (InvalidImageValueException|InvalidImageStateException $exception) {
            Log::critical($exception->getMessage(), $exception->getContext());
        } catch (ImageVariantGenerationException $exception) {
            Log::error($exception->getMessage(), $exception->getContext());
        } catch (ModelNotFoundException $exception) {
            Log::warning("Image with id {$this->imageId} not found.");
        }
    }

    protected function deleteVariantFilesAndResetState(): void
    {
        DB::transaction(function () {
            $image = Image::lockForUpdate()->findOrFail($this->imageId);

            if ($image->status !== ImageStatus::DONE) {
                throw InvalidImageStateException::make($image->status, ImageStatus::DONE, [
                    'image_id' => $this->imageId,
                    'caller' => static::class.'@deleteVariantFilesAndResetState',
                ]);
            }

            $this->deleteVariantFiles($image);

            $image->update([
                'status' => ImageStatus::QUEUED,
                'variant_files' => null,
                'processed_at' => null,
            ]);
        });
    }

    protected function deleteVariantFiles(Image $image): void
    {
        if (empty($image->variant_files)) {
            return;
        }

        foreach ($image->variant_files as $variant => $formats) {
            if ($variant === '_pending') {
                foreach ($formats as $file) {
                    $this->deleteFileQuietly($file['disk'], $file['file_name']);
                }

                continue;
            }

            foreach ($formats as $format => $file) {
                $this->deleteFileQuietly($file['disk'], $file['file_name']);
            }
        }
    }

    protected function deleteFileQuietly(string $disk, string $fileName): void
    {
        try {
            $storage = Storage::disk($disk);
            if ($storage->exists($fileName)) {
                $storage->delete($fileName);
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to delete file [{$fileName}] from disk [{$disk}]: {$e->getMessage()}");
        }
    }
}
