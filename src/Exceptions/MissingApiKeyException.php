<?php

namespace Pirabyte\ERecht24Laravel\Exceptions;

class MissingApiKeyException extends ERecht24Exception
{
    public static function make(): self
    {
        return new self('No eRecht24 API key has been configured.');
    }
}
