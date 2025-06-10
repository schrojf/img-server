<?php

namespace App\Exceptions;

use Exception;

class DownloadImageActionException extends Exception
{
    protected array $context = [];

    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Static factory to quickly create exception with context.
     */
    public static function make(string $message, int $code = 0, ?Exception $previous = null, array $context = []): self
    {
        return new self($message, $code, $previous, $context);
    }

    /**
     * Retrieve the context array for logging or debugging.
     */
    public function context(): array
    {
        return $this->context;
    }
}
