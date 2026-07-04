<?php

namespace Pirabyte\ERecht24Laravel\Exceptions;

class UnsupportedLegalTextTypeException extends ERecht24Exception
{
    public static function forType(string $type): self
    {
        return new self("The legal text type [{$type}] is not supported.");
    }
}
