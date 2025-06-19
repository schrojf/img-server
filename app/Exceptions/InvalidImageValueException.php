<?php

namespace App\Exceptions;

use LogicException;
use Throwable;

class InvalidImageValueException extends LogicException
{
    protected array $context = [];

    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public static function make(string $message, int $code = 0, ?Throwable $previous = null, array $context = []): self
    {
        return new self($message, $code, $previous, $context);
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
