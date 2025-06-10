<?php

namespace App\Exceptions;

use App\Models\Enums\ImageStatus;
use Exception;
use Throwable;

class InvalidImageStateException extends Exception
{
    public function __construct(
        public ImageStatus $currentStatus,
        public ImageStatus $expectedStatus,
        ?string $message = null,
        ?Throwable $previous = null
    ) {
        $message ??= "Invalid image state: expected '{$expectedStatus->value}', got '{$currentStatus->value}'";
        parent::__construct($message, 0, $previous);
    }

    public static function fromInvalidStateTransition(ImageStatus $current, ImageStatus $expected): self
    {
        return new self($current, $expected);
    }
}
