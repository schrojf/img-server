<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class DownloadImageActionException extends RuntimeException
{
    protected array $context = [];

    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Static factory to quickly create exception with context.
     */
    public static function make(string $message, int $code = 0, ?Throwable $previous = null, array $context = []): self
    {
        return new self($message, $code, $previous, $context);
    }

    /**
     * Retrieve the context array for logging or debugging.
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
