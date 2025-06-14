<?php

namespace App\Actions;

use App\Exceptions\DownloadImageActionException;
use App\Models\Image;
use App\Support\DownloadedFile;
use App\Support\ImageFile;
use App\Support\ImageStorage;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;

class DownloadImageAction
{
    public function __construct(
        protected TempFileDownloadAction $tempFileDownloadAction,
        protected GenerateRandomHashFileNameAction $randomHashFileNameAction,
    ) {}

    /**
     * @throws DownloadImageActionException
     */
    public function handle(Image $image): ImageFile
    {
        if (! $image->exists || empty($image->getKey())) {
            throw DownloadImageActionException::make(
                'Image model is not persisted in the database.',
                context: ['image_id' => $image->getKey()]
            );
        }

        if (empty($image->original_url)) {
            throw DownloadImageActionException::make(
                "Image [ID: {$image->getKey()}] does not have an original URL set.",
                context: ['image_id' => $image->getKey()]
            );
        }

        if (! empty($image->image_file)) {
            throw DownloadImageActionException::make(
                "Image [ID: {$image->getKey()}] already has an image_file assigned.",
                context: [
                    'image_id' => $image->getKey(),
                    'image_file' => $image->image_file,
                ],
            );
        }

        try {
            $tmpFile = $this->tempFileDownloadAction->handle($image->original_url);
        } catch (RequestException $e) {
            throw DownloadImageActionException::make(
                "Failed to download image from URL [{$image->original_url}]: ".$e->getMessage(),
                $e->getCode(),
                $e,
                [
                    'image_id' => $image->getKey(),
                    'exception_name' => get_class($e),
                    'exception_code' => $e->getCode(),
                ]
            );
        }

        if (! $tmpFile->isValidImage) {
            throw DownloadImageActionException::make(
                "Downloaded file is not a valid image for image [ID: {$image->getKey()}]. Reason: ".($tmpFile->firstError ?? 'Unknown error'),
                context: [
                    'image_id' => $image->getKey(),
                    'original_url' => $image->original_url,
                ]
            );
        }

        $fileName = $this->randomHashFileNameAction->handle().'_'.$image->id.'.'.$tmpFile->extension;

        $diskName = ImageStorage::original();
        $disk = ImageStorage::originalDisk();

        if ($disk->exists($fileName)) {
            throw DownloadImageActionException::make(
                "File collision: Generated file name '{$fileName}' already exists on disk '{$diskName}'.",
                context: [
                    'image_id' => $image->getKey(),
                ]
            );
        }

        $file = new ImageFile(
            $diskName,
            $fileName,
            $tmpFile->mimeType,
            $tmpFile->size,
            $tmpFile->dimensions['width'],
            $tmpFile->dimensions['height'],
        );

        $image = $this->updatePendingState($image, $file);

        if ($disk->putFileAs($tmpFile->path, $fileName) === false) {
            $image = $this->cleanFailedPendingState($image, $tmpFile);

            throw DownloadImageActionException::make(
                "Failed to store image file '{$fileName}' to disk '{$diskName}'.",
                context: [
                    'image_id' => $image->getKey(),
                    'tmp_file_path' => $tmpFile->path,
                ],
            );
        }

        @unlink($tmpFile->path);

        $this->updateCompletedState($image, $file);

        return $file;
    }

    protected function updatePendingState(Image $image, ImageFile $file): Image
    {
        return DB::transaction(function () use ($image, $file) {
            $image = Image::lockForUpdate()->findOrFail($image->id);

            if (! empty($image->image_file)) {
                throw DownloadImageActionException::make(
                    "Could not update pending state. Image [ID: {$image->getKey()}] already has an image_file assigned.",
                    context: [
                        'image_id' => $image->getKey(),
                        'image_file' => $image->image_file,
                    ],
                );
            }

            // Optional: $image->state = ImageStatus::DOWNLOADING_IMAGE; // It could also be PERSISTING_DOWNLOADED_IMAGE
            $image->image_file = $file->toArray();
            $image->save();

            return $image;
        });
    }

    protected function cleanFailedPendingState(Image $image, DownloadedFile $tmpFile): Image
    {
        @unlink($tmpFile->path);

        return DB::transaction(function () use ($image) {
            $image = Image::lockForUpdate()->findOrFail($image->id);

            if (empty($image->image_file)) {
                // This should trigger a new inconsistency error.
                // At this point, I have a pending state saved inside `image_file` field.
                // Also, I could check that file and perform additional cleanup, but this one file
                // is the same as which has failed to be written on to the disk.
            }

            $image->image_file = null;
            $image->save();

            return $image;
        });
    }

    protected function updateCompletedState(Image $image, ImageFile $file)
    {
        // This action is done in DownloadImageJob class.
    }
}
