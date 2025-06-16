<?php

namespace App\Variants;

use App\Exceptions\ImageVariantGenerationException;
use App\Models\Image;
use App\Support\ImageFile;
use App\Support\ImageStorage;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Image as InterventionImage;
use Intervention\Image\ImageManager;

class GenerateVariantsAction
{
    public function __construct(protected ImageManager $imageManager) {}

    public function handle(Image $imageModel): array
    {
        $this->validateImageModel($imageModel);

        $imageFile = ImageFile::fromModel($imageModel);
        $this->validateSourceImage($imageFile);

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

        $this->updatePendingState($imageModel, $pendingVariants);

        try {
            $generatedVariants = $pendingVariants->generateVariants($image);
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

    protected function updatePendingState(Image $imageModel, PendingVariants $pendingVariants): void
    {
        if (! empty($imageModel->variant_files)) {
            throw ImageVariantGenerationException::variantsAlreadyExist($imageModel);
        }

        $imageModel->variant_files = [
            '_pending' => $pendingVariants->getPendingFiles(),
        ];

        try {
            $imageModel->save();
        } catch (\Throwable $e) {
            throw ImageVariantGenerationException::databaseUpdateFailed(
                $imageModel->id,
                "update pending state for image: {$imageModel->id}",
                $e
            );
        }
    }
}
