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
        if (($this->isFile = is_file($this->path)) === false || ($fileSize = filesize($this->path)) === false) {
            $this->isValidImage = false;
            $this->mimeType = null;
            $this->extension = null;
            $this->size = null;
            $this->dimensions = null;
            $this->firstError = 'Downloaded file is not a valid file.';

            return;
        }

        if ($this->hasInvalidFileSize($this->size = $fileSize)) {
            $this->isValidImage = false;
            $this->mimeType = null;
            $this->extension = null;
            $this->dimensions = null;
            $this->firstError = 'Downloaded file is too large.';

            return;
        }

        $this->mimeType = $this->getMimeType($this->path);
        $this->extension = $this->guessExtension($this->mimeType);

        if (($this->isValidImage = $this->hasAllowedExtension($this->extension)) === false) {
            $this->dimensions = null;
            $this->firstError = 'Downloaded file is not a valid image.';

            return;
        }

        if (($this->dimensions = $this->getDimensions($this->path)) === null) {
            $this->firstError = 'Downloaded file is not a valid image.';

            return;
        }

        $this->firstError = null;
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

        $allowedExtensions = Config::get('images.downloads.allowedExtensions');

        return in_array($extension, $allowedExtensions);
    }

    /**
     * Get the dimensions of the image (if applicable).
     *
     * @return array|null
     */
    protected function getDimensions(string $filePath): ?array
    {
        if (($dimensions = @getimagesize($filePath)) === false) {
            return null;
        }

        return [
            'width' => $dimensions[0],
            'height' => $dimensions[1],
        ];
    }
}
