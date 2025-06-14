<?php

namespace App\Variants;

use App\Exceptions\ImageVariantGenerationException;
use App\Support\ImageFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Image;

class PendingVariants
{
    protected array $persistedFiles = [];

    public function __construct(
        protected readonly array $imageVariants,
        protected readonly string $baseFileName,
        protected readonly string $targetDisk,
    ) {}

    public static function fromRegistry(string $baseFileName, string $targetDisk): static
    {
        return new static(ImageVariantRegistry::all(), $baseFileName, $targetDisk);
    }

    public function getPendingFiles(): array
    {
        $pendingFiles = [];

        /** @var ImageVariant $imageVariant */
        foreach ($this->imageVariants as $imageVariant) {
            $pendingFiles = array_merge($pendingFiles, $imageVariant->simulatedEncodedFiles($this->baseFileName));
        }

        return $pendingFiles;
    }

    public function generateVariants(Image $image): array
    {
        $encodedFiles = [];
        $disk = Storage::disk($this->targetDisk);

        /** @var ImageVariant $imageVariant */
        foreach ($this->imageVariants as $imageVariant) {
            $pendingEncodings = $imageVariant->process($image);

            $size = $pendingEncodings->image->size();
            $width = $size->width();
            $height = $size->height();
            $filePathWithoutExtension = $this->baseFileName.'_'.$pendingEncodings->variantName;

            foreach ($pendingEncodings->encoders as $extension => $encoder) {
                $fileName = $filePathWithoutExtension.'.'.$extension;

                try {
                    $encoded = $pendingEncodings->image->encode($encoder);
                } catch (\Throwable $e) {
                    throw ImageVariantGenerationException::encodingError(
                        $extension,
                        $pendingEncodings->variantName,
                        $e
                    );
                }

                try {
                    if ($disk->put($fileName, $encoded) === false) {
                        throw ImageVariantGenerationException::fileSavingError(
                            $this->targetDisk,
                            $fileName,
                            $pendingEncodings->variantName,
                        );
                    }
                } catch (\Throwable $e) {
                    throw ImageVariantGenerationException::fileSavingError(
                        $this->targetDisk,
                        $fileName,
                        $pendingEncodings->variantName,
                    );
                }

                $this->persistedFiles[] = [
                    'disk' => $this->targetDisk,
                    'fileName' => $fileName,
                ];

                $encodedFiles[$pendingEncodings->variantName][$extension] = new ImageFile(
                    disk: $this->targetDisk,
                    fileName: $fileName,
                    mimeType: $encoded->mimetype(),
                    size: $encoded->size(),
                    width: $width,
                    height: $height,
                );

                $encoded = null;
            }
        }

        return $encodedFiles;
    }

    public function deleteProcessedFiles(): void
    {
        foreach ($this->persistedFiles as $file) {
            try {
                if (Storage::disk($file['disk'])->exists($file['fileName'])) {
                    Storage::disk($file['disk'])->delete($file['fileName']);
                    Log::debug('Cleaned up partial file', $file);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to clean up partial file', array_merge($file, [
                    'error' => $e->getMessage(),
                ]));
            }
        }
    }
}
