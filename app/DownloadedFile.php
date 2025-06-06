<?php

namespace App;

use Symfony\Component\Mime\MimeTypes;

class DownloadedFile
{
    public static array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

    public static int $maxFileSize = 30_000_000;

    public function __construct(protected string $path)
    {
    }

    public function isFile(): bool
    {
        return is_file($this->path);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function getSize(): int|false
    {
        if (! $this->isFile()) {
            return false;
        }

        return filesize($this->path);
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
    public function guessExtension(): ?string
    {
        if (! class_exists(MimeTypes::class)) {
            throw new \LogicException('You cannot guess the extension as the Mime component is not installed. Try running "composer require symfony/mime".');
        }

        return MimeTypes::getDefault()->getExtensions($this->getMimeType() ?? '')[0] ?? null;
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
    public function getMimeType(): ?string
    {
        if (! class_exists(MimeTypes::class)) {
            throw new \LogicException('You cannot guess the mime type as the Mime component is not installed. Try running "composer require symfony/mime".');
        }

        if (! $this->isFile()) {
            return null;
        }

        return MimeTypes::getDefault()->guessMimeType($this->path());
    }

    public function isValidImage(): bool
    {
        if (! $this->isFile()) {
            return false;
        }

        if (($size = $this->getSize()) === false) {
            return false;
        }

        if (static::$maxFileSize > 0 && $size > static::$maxFileSize) {
            return false;
        }

        return $this->path() !== '' && in_array($this->guessExtension(), static::$allowedExtensions);
    }

    /**
     * Get the dimensions of the image (if applicable).
     *
     * @return array|null
     */
    public function dimensions(): array|false
    {
        return @getimagesize($this->path());
    }
}
