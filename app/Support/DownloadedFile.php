<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;
use Symfony\Component\Mime\MimeTypes;

readonly class DownloadedFile
{
    public bool $isFile;

    public bool $isValidImage;

    public ?string $mimeType;

    public ?string $extension;

    public ?int $size;

    public ?array $dimensions;

    public ?string $firstError;

    public function __construct(public string $path)
    {
        $this->initialize();
    }

    protected function initialize(): void
    {
        $isFile = is_file($this->path);
        $size = $isFile ? filesize($this->path) : false;

        if (! $isFile || $size === false) {
            $this->setFailure(
                isFile: false,
                size: null,
                mimeType: null,
                extension: null,
                dimensions: null,
                error: 'Downloaded file is not a valid file.'
            );

            return;
        }

        if ($this->hasInvalidFileSize($size)) {
            $this->setFailure(
                isFile: true,
                size: $size,
                mimeType: null,
                extension: null,
                dimensions: null,
                error: 'Downloaded file is too large.'
            );

            return;
        }

        $mimeType = $this->getMimeType($this->path);
        $extension = $this->guessExtension($mimeType);

        if (! $this->hasAllowedExtension($extension)) {
            $this->setFailure(
                isFile: true,
                size: $size,
                mimeType: $mimeType,
                extension: $extension,
                dimensions: null,
                error: 'Downloaded file is not a valid image.'
            );

            return;
        }

        $dimensions = $this->getDimensions($this->path);

        if ($dimensions === null) {
            $this->setFailure(
                isFile: true,
                size: $size,
                mimeType: $mimeType,
                extension: $extension,
                dimensions: null,
                error: 'Downloaded file is not a valid image.'
            );

            return;
        }

        // All good — set all properties as valid
        $this->isFile = true;
        $this->size = $size;
        $this->mimeType = $mimeType;
        $this->extension = $extension;
        $this->dimensions = $dimensions;
        $this->isValidImage = true;
        $this->firstError = null;
    }

    protected function setFailure(
        bool $isFile,
        ?int $size,
        ?string $mimeType,
        ?string $extension,
        ?array $dimensions,
        string $error
    ): void {
        $this->isFile = $isFile;
        $this->size = $size;
        $this->mimeType = $mimeType;
        $this->extension = $extension;
        $this->dimensions = $dimensions;
        $this->isValidImage = false;
        $this->firstError = $error;
    }

    protected function hasInvalidFileSize(int $size): bool
    {
        $maxFileSize = Config::get('images.downloads.maxFileSize');

        return $maxFileSize > 0 && $size > $maxFileSize;
    }

    /**
     * Returns the mime type of the file.
     *
     * The mime type is guessed using a MimeTypeGuesserInterface instance,
     * which uses finfo_file() then the "file" system binary,
     * depending on which of those are available.
     *
     * @see MimeTypes
     */
    public function getMimeType(string $filePath): ?string
    {
        if (! class_exists(MimeTypes::class)) {
            throw new \LogicException('You cannot guess the mime type as the Mime component is not installed. Try running "composer require symfony/mime".');
        }

        return MimeTypes::getDefault()->guessMimeType($filePath);
    }

    /**
     * Returns the extension based on the mime type.
     *
     * If the mime type is unknown, returns null.
     *
     * This method uses the mime type as guessed by getMimeType()
     * to guess the file extension.
     *
     * @see MimeTypes
     * @see getMimeType()
     */
    public function guessExtension(?string $mimeType): ?string
    {
        if (! class_exists(MimeTypes::class)) {
            throw new \LogicException('You cannot guess the extension as the Mime component is not installed. Try running "composer require symfony/mime".');
        }

        return MimeTypes::getDefault()->getExtensions($mimeType ?? '')[0] ?? null;
    }

    protected function hasAllowedExtension(?string $extension): bool
    {
        if (is_null($extension)) {
            return false;
        }

        $allowed = Config::get('images.downloads.allowedExtensions', []);

        return in_array($extension, $allowed, true);
    }

    /**
     * Get the dimensions of the image (if applicable).
     */
    protected function getDimensions(string $filePath): ?array
    {
        $dimensions = @getimagesize($filePath);

        return $dimensions === false ? null : [
            'width' => $dimensions[0],
            'height' => $dimensions[1],
        ];
    }
}
