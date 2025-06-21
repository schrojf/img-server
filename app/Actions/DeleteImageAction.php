<?php

namespace App\Actions;

use App\Models\Enums\ImageStatus;
use App\Models\Image;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeleteImageAction
{
    public function handle(int $imageId): bool
    {
        $image = $this->markDeletingStateAndGetImage($imageId);

        $this->deleteVariants($image);
        $this->deleteImage($image);

        return $image->delete();
    }

    protected function markDeletingStateAndGetImage(int $imageId): Image
    {
        return DB::transaction(function () use ($imageId) {
            $image = Image::lockForUpdate()->findOrFail($imageId);

            $image->update([
                'status' => ImageStatus::DELETING,
            ]);

            return $image;
        });
    }

    protected function deleteVariants(Image $image): void
    {
        if (empty($image->variant_files)) {
            return;
        }

        if (isset($image->variant_files['_pending'])) {
            foreach ($image->variant_files['_pending'] as $pendingVariant) {
                $this->deleteFileIfExists($pendingVariant['disk'], $pendingVariant['file_name']);
            }
        }

        foreach ($image->variant_files as $variant => $formats) {
            if ($variant === '_pending') {
                continue;
            }

            foreach ($formats as $format => $file) {
                $this->deleteFileIfExists($file['disk'], $file['file_name']);
            }
        }
    }

    protected function deleteImage(Image $image): void
    {
        if (empty($image->image_file)) {
            return;
        }

        $this->deleteFileIfExists($image->image_file['disk'], $image->image_file['file_name']);
    }

    protected function deleteFileIfExists(string $disk, string $fileName): void
    {
        try {
            $disk = Storage::disk($disk);
            if ($disk->exists($fileName)) {
                $disk->delete($fileName);
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to delete file [$fileName] from disk [$disk] up partial file: ".$e->getMessage());
        }
    }
}
