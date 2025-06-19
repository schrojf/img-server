<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ImageVariantGenerationException extends RuntimeException
{
    protected array $context = [];

    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public static function sourceImageNotFound(string $disk, string $fileName): self
    {
        return new self(
            "Source image file not found on disk '{$disk}': {$fileName}",
            1001,
            null,
            ['disk' => $disk, 'fileName' => $fileName]
        );
    }

    public static function sourceImageUnreadable(string $disk, string $fileName, ?Throwable $previous = null): self
    {
        return new self(
            "Source image file cannot be read from disk '{$disk}': {$fileName}",
            1002,
            $previous,
            ['disk' => $disk, 'fileName' => $fileName]
        );
    }

    public static function modifierFailed(string $modifierClass, string $variantName, ?Throwable $previous = null): self
    {
        return new self(
            "Image modifier '{$modifierClass}' failed for variant '{$variantName}'",
            1003,
            $previous,
            ['modifier' => $modifierClass, 'variant' => $variantName]
        );
    }

    public static function encodingError(string $extension, string $variantName, ?Throwable $previous = null): self
    {
        return new self(
            "Failed to encode image variant '{$variantName}' to format '{$extension}'",
            1004,
            $previous,
            ['extension' => $extension, 'variant' => $variantName]
        );
    }

    public static function fileSavingError(string $disk, string $fileName, string $variantName): self
    {
        return new self(
            "Failed to save variant '{$variantName}' file to disk '{$disk}': {$fileName}",
            1005,
            null,
            ['disk' => $disk, 'fileName' => $fileName, 'variant' => $variantName]
        );
    }
}
