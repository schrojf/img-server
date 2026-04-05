<?php

namespace App\Console\Commands;

use App\Actions\DownloadImageAction;
use App\Actions\GenerateVariantsAction;
use App\Models\Enums\ImageStatus;
use App\Models\Image;
use App\Support\ImageFile;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RetryFailedImagesCommand extends Command
{
    protected $signature = 'images:retry {id? : Retry a specific image by ID} {--all : Retry all failed and expired images}';

    protected $description = 'Diagnose and retry failed or expired images';

    public function handle(
        DownloadImageAction $downloadImageAction,
        GenerateVariantsAction $generateVariantsAction,
    ): int {
        $images = $this->getImages();

        if ($images === null) {
            return self::FAILURE;
        }

        if ($images->isEmpty()) {
            $this->info('No failed or expired images found.');

            return self::SUCCESS;
        }

        $succeeded = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($images as $image) {
            $diagnosis = $this->diagnose($image);

            if ($diagnosis === null) {
                $this->line("[Image #{$image->id}] No issue found, skipping.");
                $skipped++;

                continue;
            }

            $this->line("[Image #{$image->id}] {$diagnosis['message']}");

            try {
                $this->resetState($image, $diagnosis);

                if ($diagnosis['needs_download']) {
                    $downloadImageAction->handle($image->id);
                }

                $generateVariantsAction->handle($image->id);

                $this->info("[Image #{$image->id}] OK");
                $succeeded++;
            } catch (\Throwable $e) {
                $this->error("[Image #{$image->id}] FAILED: {$e->getMessage()}");
                Log::error("Retry failed for image #{$image->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $total = $succeeded + $failed + $skipped;
        $this->newLine();
        $this->info("Retried: {$total}, Succeeded: {$succeeded}, Failed: {$failed}, Skipped: {$skipped}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Collection<int, Image>|null
     */
    protected function getImages()
    {
        $id = $this->argument('id');

        if ($id !== null && $this->option('all')) {
            $this->error('Cannot use both a specific ID and --all.');

            return null;
        }

        if ($id === null && ! $this->option('all')) {
            $this->error('Provide an image ID or use --all.');

            return null;
        }

        $retryableStatuses = [ImageStatus::FAILED, ImageStatus::EXPIRED];

        if ($id !== null) {
            $image = Image::find($id);

            if ($image === null) {
                $this->error("Image #{$id} not found.");

                return null;
            }

            if (! in_array($image->status, $retryableStatuses)) {
                $this->error("Image #{$id} has status '{$image->status->value}' and is not eligible for retry.");

                return null;
            }

            return collect([$image]);
        }

        return Image::whereIn('status', $retryableStatuses)->get();
    }

    /**
     * Diagnose the image and return what needs to happen, or null if it looks healthy.
     */
    protected function diagnose(Image $image): ?array
    {
        // Step 1: Check image_file
        if (empty($image->image_file)) {
            return [
                'needs_download' => true,
                'clear_image_file' => true,
                'clear_variant_files' => true,
                'message' => 'image_file is null, re-downloading and generating variants...',
            ];
        }

        $imageFile = ImageFile::fromArray($image->image_file);

        if (! $imageFile->stillExists() || Storage::disk($imageFile->disk)->size($imageFile->fileName) === 0) {
            $this->deleteFileQuietly($imageFile->disk, $imageFile->fileName);

            return [
                'needs_download' => true,
                'clear_image_file' => true,
                'clear_variant_files' => true,
                'message' => 'image_file missing or empty on disk, re-downloading and generating variants...',
            ];
        }

        // Step 2: Check variant_files
        if (empty($image->variant_files)) {
            return [
                'needs_download' => false,
                'clear_image_file' => false,
                'clear_variant_files' => true,
                'message' => 'variant_files is null, generating variants...',
            ];
        }

        if (isset($image->variant_files['_pending'])) {
            $this->deleteVariantFiles($image);

            return [
                'needs_download' => false,
                'clear_image_file' => false,
                'clear_variant_files' => true,
                'message' => 'variant generation was interrupted (_pending), cleaning up and regenerating...',
            ];
        }

        // Check all variant files exist on disk
        $totalFiles = 0;
        $missingFiles = 0;

        foreach ($image->variant_files as $variant => $formats) {
            foreach ($formats as $format => $file) {
                $totalFiles++;
                if (! Storage::disk($file['disk'])->exists($file['file_name'])) {
                    $missingFiles++;
                }
            }
        }

        if ($missingFiles > 0) {
            $this->deleteVariantFiles($image);

            return [
                'needs_download' => false,
                'clear_image_file' => false,
                'clear_variant_files' => true,
                'message' => "variant_files incomplete ({$missingFiles}/{$totalFiles} files missing), regenerating variants...",
            ];
        }

        // Everything looks fine
        return null;
    }

    protected function resetState(Image $image, array $diagnosis): void
    {
        DB::transaction(function () use ($image, $diagnosis) {
            $image = Image::lockForUpdate()->findOrFail($image->id);

            $updates = [
                'status' => ImageStatus::QUEUED,
                'last_error' => null,
            ];

            if ($diagnosis['clear_image_file']) {
                $updates['image_file'] = null;
                $updates['downloaded_at'] = null;
            }

            if ($diagnosis['clear_variant_files']) {
                $updates['variant_files'] = null;
                $updates['processed_at'] = null;
            }

            $image->update($updates);
        });
    }

    protected function deleteVariantFiles(Image $image): void
    {
        if (empty($image->variant_files)) {
            return;
        }

        if (isset($image->variant_files['_pending'])) {
            foreach ($image->variant_files['_pending'] as $pendingVariant) {
                $this->deleteFileQuietly($pendingVariant['disk'], $pendingVariant['file_name']);
            }
        }

        foreach ($image->variant_files as $variant => $formats) {
            if ($variant === '_pending') {
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
