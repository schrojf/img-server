<?php

namespace App\Variants;

use App\Exceptions\ImageVariantGenerationException;
use App\Models\Image;
use App\Support\ImageFile;
use App\Support\ImageStorage;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Image as InterventionImage;
use Intervention\Image\ImageManager;

class GenerateVariantsAction
{
    public function handle(Image $imageModel): array
    {
        $this->validateImageModel($imageModel);

        $imageFile = ImageFile::fromModel($imageModel);
        $this->validateSourceImage($imageFile);

        $imageFileStream = Storage::disk($imageFile->disk)->readStream($imageFile->fileName);

        try {
            /** @var InterventionImage $image */
            $image = $this->manager()->read($imageFileStream);
        } catch (\Throwable $e) {
            throw ImageVariantGenerationException::sourceImageUnreadable(
                $imageFile->disk,
                $imageFile->fileName,
                $e
            );
        }

        $targetDisk = ImageStorage::variant();
        $fileNamePathWithoutExtension = $this->removeFileExtension($imageFile->fileName);
        $pendingVariants = PendingVariants::fromRegistry($fileNamePathWithoutExtension, $targetDisk);

        $this->updatePendingState($imageModel, $pendingVariants);

        try {
            $generatedVariants = $pendingVariants->generateVariants($image);
            $this->updateFinalState($imageModel, $generatedVariants);
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

    protected function validateImageModel(Image $imageModel): void
    {
        if (! $imageModel->exists) {
            throw new \InvalidArgumentException('Image model must exist in database');
        }

        if (! empty($imageModel->variant_files)) {
            // Todo: Error variants already generated or pending variants are present
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

    protected function updatePendingState(Image $imageModel, PendingVariants $pendingVariants): void
    {
        try {
            $toBeEncoded = $pendingVariants->getPendingFiles();
            $variantFiles = $imageModel->variant_files ?? [];
            $variantFiles['_pending'] = $toBeEncoded;
            $imageModel->save();
        } catch (\Throwable $e) {
            throw ImageVariantGenerationException::databaseUpdateFailed(
                $imageModel->id,
                "update pending state for image: {$imageModel->id}",
                $e
            );
        }
    }

    protected function updateFinalState(Image $imageModel, array $generatedVariants): void
    {
        try {
            // Final state replaces all variant files
            $imageModel->variant_files = $generatedVariants;
            $imageModel->save();
        } catch (\Throwable $e) {
            throw ImageVariantGenerationException::databaseUpdateFailed(
                $imageModel->id,
                "update final state for image: {$imageModel->id}",
                $e
            );
        }
    }

    protected function manager(): ImageManager
    {
        $driver = config('images.driver', 'gd');

        return new ImageManager(match ($driver) {
            'gd', 'GD', 'Gd', 'GdDriver' => new GdDriver,
            'imagick', 'Imagick', 'ImagickDriver' => new ImagickDriver,
            default => app($driver),
        });
    }
}
