<?php

namespace App\Exceptions;

use App\Models\Enums\ImageStatus;
use LogicException;
use Throwable;

class InvalidImageStateException extends LogicException
{
    protected array $context = [];

    public function __construct(
        public ImageStatus $currentStatus,
        public ImageStatus $expectedStatus,
        ?string $message = null,
        ?Throwable $previous = null,
        array $context = [],
    ) {
        $message ??= "Invalid image state: expected '{$expectedStatus->value}', got '{$currentStatus->value}'";
        parent::__construct($message, 0, $previous);
        $this->context = $context;
    }

    public static function make(ImageStatus $current, ImageStatus $expected, array $additionalContext = []): self
    {
        return new self($current, $expected, context: array_merge($additionalContext, [
            'current_status' => $current->value,
            'expected_status' => $expected->value,
        ]));
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
