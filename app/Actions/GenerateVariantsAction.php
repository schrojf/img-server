<?php

namespace App\Actions;

use App\Exceptions\ImageVariantGenerationException;
use App\Exceptions\InvalidImageStateException;
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
        $image = $this->updateQueuedStateAndGetImage($imageId);

        try {
            $this->validateImageModel($image);

            $imageFile = ImageFile::fromModel($image);
            $this->validateSourceImage($imageFile);

            return $this->generateImageVariants($imageId, $imageFile);
        } catch (ImageVariantGenerationException $exception) {
            $this->updateFailedState($imageId, $exception->getMessage());

            throw $exception;
        }
    }

    protected function updateQueuedStateAndGetImage(int $imageId): Image
    {
        return DB::transaction(function () use ($imageId) {
            $image = Image::lockForUpdate()->findOrFail($imageId);

            if ($image->status !== ImageStatus::IMAGE_DOWNLOADED) {
                throw InvalidImageStateException::fromInvalidStateTransition($image->status, ImageStatus::IMAGE_DOWNLOADED, [
                    'image_id' => $imageId,
                ]);
            }

            $image->update([
                'status' => ImageStatus::GENERATING_VARIANTS,
            ]);

            return $image;
        });
    }

    protected function validateImageModel(Image $imageModel): void
    {
        if (! $imageModel->exists) {
            throw new \InvalidArgumentException('Image model must exist in database');
        }

        if (! empty($imageModel->variant_files)) {
            throw ImageVariantGenerationException::variantsAlreadyExist($imageModel);
        }
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

            if ($image->status !== ImageStatus::GENERATING_VARIANTS) {
                throw InvalidImageStateException::fromInvalidStateTransition($image->status, ImageStatus::GENERATING_VARIANTS, [
                    'image_id' => $imageId,
                ]);
            }

            if (! empty($imageModel->variant_files)) {
                throw ImageVariantGenerationException::variantsAlreadyExist($imageModel);
            }

            $image->variant_files = [
                '_pending' => $pendingVariants->getPendingFiles(),
            ];
            // Optional intermediate state: $image->state = ImageStatus::PERSISTING_GENERATED_VARIANTS;

            try {
                $image->save();
            } catch (\Throwable $e) {
                throw ImageVariantGenerationException::databaseUpdateFailed(
                    $image->id,
                    "update pending state for image: {$image->id}",
                    $e
                );
            }
        });
    }

    protected function updateCompletedState(int $imageId, array $generatedVariants): void
    {
        DB::transaction(function () use ($imageId, $generatedVariants) {
            $image = Image::lockForUpdate()->findOrFail($imageId);

            if ($image->status !== ImageStatus::GENERATING_VARIANTS) {
                throw InvalidImageStateException::fromInvalidStateTransition($image->status, ImageStatus::GENERATING_VARIANTS, [
                    'image_id' => $imageId,
                ]);
            }

            // Optional: Check if all $image->variant_files['_pending'] files are the same as $generatedVariants

            $image->status = ImageStatus::DONE;
            $image->variant_files = $generatedVariants;
            $image->save();
        });
    }

    protected function updateFailedState(int $imageId, string $errorMessage): void
    {
        DB::transaction(function () use ($imageId, $errorMessage) {
            $image = Image::lockForUpdate()->findOrFail($imageId);

            if ($image->status !== ImageStatus::GENERATING_VARIANTS) {
                throw InvalidImageStateException::fromInvalidStateTransition($image->status, ImageStatus::GENERATING_VARIANTS, [
                    'image_id' => $image->id,
                    'error_message' => $errorMessage,
                ]);
            }

            $image->status = ImageStatus::FAILED;
            $image->last_error = $errorMessage;
            $image->save();
        });
    }
}
