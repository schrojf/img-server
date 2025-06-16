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
            $this->clean($tmpFile);

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
            $this->clean($tmpFile);

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

        try {
            $image = $this->updatePendingState($image, $file);
        } catch (\Throwable $e) {
            $this->clean($tmpFile);

            throw $e;
        }

        if ($disk->putFileAs($tmpFile->path, $fileName) === false) {
            $this->clean($tmpFile);

            throw DownloadImageActionException::make(
                "Failed to store image file '{$fileName}' to disk '{$diskName}'.",
                context: [
                    'image_id' => $image->getKey(),
                    'tmp_file_path' => $tmpFile->path,
                ],
            );
        }

        $this->clean($tmpFile);

        return $file;
    }

    protected function updatePendingState(Image $image, ImageFile $file): Image
    {
        return DB::transaction(function () use ($image, $file) {
            $image = Image::lockForUpdate()->findOrFail($image->id);

            if (! empty($image->image_file)) {
                throw DownloadImageActionException::make(
                    "Image [ID: {$image->getKey()}] already has an image_file assigned.",
                    context: [
                        'image_id' => $image->getKey(),
                        'image_file' => $image->image_file,
                    ],
                );
            }

            // $image->state = ImageStatus::PERSISTING_DOWNLOADED_IMAGE; // Optional intermediate state
            $image->image_file = ['_pending' => $file->toArray()];
            $image->save();

            return $image;
        });
    }

    protected function clean(DownloadedFile $tmpFile): bool
    {
        return @unlink($tmpFile->path);
    }
}
