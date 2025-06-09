<?php

namespace App\Actions;

use App\Exceptions\DownloadImageActionException;
use App\Models\Image;
use App\Support\ImageFile;
use App\Support\ImageStorage;
use Illuminate\Http\Client\RequestException;

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
            throw new DownloadImageActionException("Image model is not persisted in the database.");
        }

        if (empty($image->original_url)) {
            throw new DownloadImageActionException("Image [ID: {$image->getKey()}] does not have an original URL set.");
        }

        if (! empty($image->image_file)) {
            throw new DownloadImageActionException("Image [ID: {$image->getKey()}] already has an image_file assigned.");
        }

        try {
            $tmpFile = $this->tempFileDownloadAction->handle($image->original_url);
        } catch (RequestException $e) {
            throw new DownloadImageActionException(
                "Failed to download image from URL [{$image->original_url}]: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }

        if (! $tmpFile->isValidImage) {
            throw new DownloadImageActionException(
                "Downloaded file is not a valid image for image [ID: {$image->getKey()}]. Reason: " . ($tmpFile->firstError ?? 'Unknown error')
            );
        }

        $fileName = $this->randomHashFileNameAction->handle() . '_' . $image->id . '.' . $tmpFile->extension;

        $diskName = ImageStorage::original();
        $disk = ImageStorage::originalDisk();

        if ($disk->exists($fileName)) {
            throw new DownloadImageActionException("File collision: Generated file name '{$fileName}' already exists on disk '{$diskName}'.");
        }

        $file = new ImageFile(
            $diskName,
            $fileName,
            $tmpFile->mimeType,
            $tmpFile->size,
            $tmpFile->dimensions['width'],
            $tmpFile->dimensions['height'],
        );

        $image->image_file = $file->toArray();
        $image->save();

        if (false === $disk->putFileAs($tmpFile->path, $fileName)) {
            $image->image_file = null;
            $image->save();

            throw new DownloadImageActionException("Failed to store image file '{$fileName}' to disk '{$diskName}'.");
        }

        // TODO: Delete temporary file from $tmpFile->path here.

        return $file;
    }
}
