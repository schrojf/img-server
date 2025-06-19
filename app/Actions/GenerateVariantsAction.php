<?php

namespace App\Actions;

use App\Exceptions\ImageVariantGenerationException;
use App\Exceptions\InvalidImageStateException;
use App\Exceptions\InvalidImageValueException;
use App\Models\Enums\ImageStatus;
use App\Models\Image;
use App\Support\ImageFile;
use App\Support\ImageStorage;
use App\Variants\PendingVariants;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Image as InterventionImage;
use Intervention\Image\ImageManager;

class GenerateVariantsAction
{
    public function __construct(protected ImageManager $imageManager) {}

    public function handle(int $imageId): array
    {
        $image = $this->findAndValidateDownloadImageModel($imageId);

        try {
            $imageFile = ImageFile::fromModel($image);
            $this->validateSourceImage($imageFile);

            return $this->generateImageVariants($imageId, $imageFile);
        } catch (ImageVariantGenerationException $exception) {
            $this->updateFailedState($imageId, $exception->getMessage());

            throw $exception;
        }
    }

    protected function findAndValidateDownloadImageModel(int $imageId): Image
    {
        return DB::transaction(function () use ($imageId) {
            $image = Image::lockForUpdate()->findOrFail($imageId);

            if ($image->status !== ImageStatus::PROCESSING) {
                throw InvalidImageStateException::make($image->status, ImageStatus::PROCESSING, [
                    'image_id' => $imageId,
                    'caller' => static::class.'@findAndValidateDownloadImageModel',
                ]);
            }

            if (empty($image->image_file)) {
                throw InvalidImageValueException::make(
                    "Image [ID: {$image->id}] must have an image_file.",
                    context: [
                        'image_id' => $imageId,
                        'caller' => static::class.'@findAndValidateDownloadImageModel',
                        'current_status' => $image->status->value,
                    ],
                );
            }

            if (! empty($image->variant_files)) {
                throw InvalidImageValueException::make(
                    "Image [ID: {$image->id}] already has a variant_files assigned.",
                    context: [
                        'image_id' => $imageId,
                        'caller' => static::class.'@findAndValidateDownloadImageModel',
                        'current_status' => $image->status->value,
                        'variant_files' => $image->variant_files,
                    ],
                );
            }

            return $image;
        });
    }

    protected function validateSourceImage(ImageFile $imageFile): void
    {
        if (! Storage::disk($imageFile->disk)->exists($imageFile->fileName)) {
            throw ImageVariantGenerationException::sourceImageNotFound(
                $imageFile->disk,
                $imageFile->fileName
            );
        }
    }

    protected function generateImageVariants(int $imageId, ImageFile $imageFile): array
    {
        $imageFileStream = Storage::disk($imageFile->disk)->readStream($imageFile->fileName);

        try {
            /** @var InterventionImage $image */
            $image = $this->imageManager->read($imageFileStream);
        } catch (\Throwable $e) {
            throw ImageVariantGenerationException::sourceImageUnreadable(
                $imageFile->disk,
                $imageFile->fileName,
                $e
            );
        } finally {
            if (is_resource($imageFileStream)) {
                fclose($imageFileStream);
            }
        }

        $targetDisk = ImageStorage::variant();
        $fileNamePathWithoutExtension = $this->removeFileExtension($imageFile->fileName);
        $pendingVariants = PendingVariants::fromRegistry($fileNamePathWithoutExtension, $targetDisk);

        $this->updatePendingState($imageId, $pendingVariants);

        try {
            $generatedVariants = $pendingVariants->generateVariants($image);

            $this->updateCompletedState($imageId, $generatedVariants);
        } catch (\Throwable $e) {
            $pendingVariants->deleteProcessedFiles();

            throw $e;
        }

        return $generatedVariants;
    }

    protected function removeFileExtension(string $fileNamePath): string
    {
        return pathinfo($fileNamePath, PATHINFO_DIRNAME)
            .DIRECTORY_SEPARATOR
            .pathinfo($fileNamePath, PATHINFO_FILENAME);
    }

    protected function updatePendingState(int $imageId, PendingVariants $pendingVariants): void
    {
        DB::transaction(function () use ($imageId, $pendingVariants) {
            $image = Image::lockForUpdate()->findOrFail($imageId);

            if ($image->status !== ImageStatus::PROCESSING) {
                throw InvalidImageStateException::make($image->status, ImageStatus::PROCESSING, [
                    'image_id' => $imageId,
                    'caller' => static::class.'@updatePendingState',
                ]);
            }

            if (! empty($image->variant_files)) {
                throw InvalidImageValueException::make(
                    "Image [ID: {$image->id}] already has a variant_files assigned.",
                    context: [
                        'image_id' => $imageId,
                        'caller' => static::class.'@updatePendingState',
                        'current_status' => $image->status->value,
                        'variant_files' => $image->variant_files,
                    ],
                );
            }

            $image->variant_files = [
                '_pending' => $pendingVariants->getPendingFiles(),
            ];

            $image->save();
        });
    }

    protected function updateCompletedState(int $imageId, array $generatedVariants): void
    {
        DB::transaction(function () use ($imageId, $generatedVariants) {
            $image = Image::lockForUpdate()->findOrFail($imageId);

            if ($image->status !== ImageStatus::PROCESSING) {
                throw InvalidImageStateException::make($image->status, ImageStatus::PROCESSING, [
                    'image_id' => $imageId,
                    'caller' => static::class.'@updateCompletedState',
                ]);
            }

            $image->status = ImageStatus::DONE;
            $image->variant_files = $generatedVariants;
            $image->save();
        });
    }

    protected function updateFailedState(int $imageId, string $errorMessage): void
    {
        DB::transaction(function () use ($imageId, $errorMessage) {
            $image = Image::lockForUpdate()->findOrFail($imageId);

            if ($image->status !== ImageStatus::PROCESSING) {
                throw InvalidImageStateException::make($image->status, ImageStatus::PROCESSING, [
                    'image_id' => $image->id,
                    'caller' => static::class.'@updateFailedState',
                    'error_message' => $errorMessage,
                ]);
            }

            $image->status = ImageStatus::FAILED;
            $image->last_error = $errorMessage;
            $image->save();
        });
    }
}
