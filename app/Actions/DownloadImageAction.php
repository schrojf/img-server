<?php

namespace App\Actions;

use App\Data\DownloadableImage;
use App\Exceptions\DownloadImageActionException;
use App\Exceptions\InvalidImageStateException;
use App\Exceptions\InvalidImageValueException;
use App\Models\Enums\ImageStatus;
use App\Models\Image;
use App\Support\DownloadedFile;
use App\Support\ImageFile;
use App\Support\ImageStorage;
use Illuminate\Support\Facades\DB;

class DownloadImageAction
{
    public function __construct(
        protected TempFileDownloadAction $tempFileDownloadAction,
        protected GenerateRandomHashFileNameAction $randomHashFileNameAction,
    ) {}

    public function handle(int $imageId): ImageFile
    {
        $imageData = $this->updateQueuedStateToProcessingAndGetImageData($imageId);

        try {
            return $this->downloadImage($imageData);
        } catch (DownloadImageActionException $exception) {
            $this->updateFailedState($imageData->id, $exception->getMessage());

            throw $exception;
        }
    }

    protected function downloadImage(DownloadableImage $image): ImageFile
    {
        try {
            $tmpFile = $this->tempFileDownloadAction->handle($image->url);
        } catch (\Throwable $e) {
            throw DownloadImageActionException::make(
                "Failed to download image from URL [{$image->url}]: ".$e->getMessage(),
                $e->getCode(),
                $e,
                [
                    'image_id' => $image->id,
                    'exception_name' => get_class($e),
                    'exception_code' => $e->getCode(),
                ]
            );
        }

        if (! $tmpFile->isValidImage) {
            $this->clean($tmpFile);

            throw DownloadImageActionException::make(
                "Downloaded file is not a valid image for image [ID: {$image->id}]. Reason: ".($tmpFile->firstError ?? 'Unknown error'),
                context: [
                    'image_id' => $image->id,
                    'original_url' => $image->url,
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
                    'image_id' => $image->id,
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
            $this->setDownloadedImageFile($image->id, $file);
        } catch (\Throwable $e) {
            $this->clean($tmpFile);

            throw $e;
        }

        try {
            $status = $disk->putFileAs($tmpFile->path, $fileName);
        } catch (\Throwable $e) {
            $this->clean($tmpFile);

            throw DownloadImageActionException::make(
                "Failed to store image file '{$fileName}' to disk '{$diskName}'. [".get_class($e)."]: {$e->getMessage()}",
                $e->getCode(),
                $e,
                [
                    'image_id' => $image->id,
                    'tmp_file_path' => $tmpFile->path,
                ],
            );
        }

        if ($status === false) {
            $this->clean($tmpFile);

            throw DownloadImageActionException::make(
                "Failed to store image file '{$fileName}' to disk '{$diskName}'.",
                context: [
                    'image_id' => $image->id,
                    'tmp_file_path' => $tmpFile->path,
                ],
            );
        }

        $this->clean($tmpFile);

        return $file;
    }

    protected function updateQueuedStateToProcessingAndGetImageData(int $imageId): DownloadableImage
    {
        return DB::transaction(function () use ($imageId) {
            $image = Image::lockForUpdate()->findOrFail($imageId);

            if ($image->status !== ImageStatus::QUEUED) {
                throw InvalidImageStateException::make($image->status, ImageStatus::QUEUED, [
                    'image_id' => $imageId,
                    'caller' => static::class.'@updateQueuedStateToProcessingAndGetImageData',
                ]);
            }

            if (! empty($image->image_file)) {
                throw InvalidImageValueException::make(
                    "Image [ID: {$image->getKey()}] already has an image_file assigned.",
                    context: [
                        'image_id' => $imageId,
                        'caller' => static::class.'@updateQueuedStateToProcessingAndGetImageData',
                        'current_status' => $image->status->value,
                        'image_file' => $image->image_file,
                    ],
                );
            }

            $image->update([
                'status' => ImageStatus::PROCESSING,
            ]);

            return new DownloadableImage(
                $image->id,
                $image->original_url,
            );
        });
    }

    protected function setDownloadedImageFile(int $imageId, ImageFile $file): void
    {
        DB::transaction(function () use ($imageId, $file) {
            $image = Image::lockForUpdate()->findOrFail($imageId);

            if ($image->status !== ImageStatus::PROCESSING) {
                throw InvalidImageStateException::make($image->status, ImageStatus::PROCESSING, [
                    'image_id' => $imageId,
                    'caller' => static::class.'@setDownloadedImageFile',
                ]);
            }

            if (! empty($image->image_file)) {
                throw InvalidImageValueException::make(
                    "Image [ID: {$image->getKey()}] already has an image_file assigned.",
                    context: [
                        'image_id' => $imageId,
                        'caller' => static::class.'@setDownloadedImageFile',
                        'current_status' => $image->status->value,
                        'image_file' => $image->image_file,
                    ],
                );
            }

            $image->image_file = $file->toArray();
            $image->save();
        });
    }

    protected function updateFailedState(int $imageId, string $error): void
    {
        DB::transaction(function () use ($imageId, $error) {
            $image = Image::lockForUpdate()->findOrFail($imageId);

            if ($image->status !== ImageStatus::PROCESSING) {
                throw InvalidImageStateException::make($image->status, ImageStatus::PROCESSING, [
                    'image_id' => $imageId,
                    'caller' => static::class.'@updateFailedState',
                    'error_message' => $error,
                ]);
            }

            $image->status = ImageStatus::FAILED;
            $image->last_error = $error;
            $image->save();
        });
    }

    protected function clean(DownloadedFile $tmpFile): bool
    {
        return @unlink($tmpFile->path);
    }
}
