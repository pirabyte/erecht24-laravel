<?php

namespace Pirabyte\ERecht24Laravel\Exceptions;

use RuntimeException;
use Throwable;

class ERecht24Exception extends RuntimeException
{
    public static function fromThrowable(Throwable $exception): self
    {
        return new self(
            $exception->getMessage() ?: 'The eRecht24 SDK request failed.',
            (int) $exception->getCode(),
            $exception,
        );
    }
}
