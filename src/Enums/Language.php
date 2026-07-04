<?php

namespace Pirabyte\ERecht24Laravel\Enums;

enum Language: string
{
    case German = 'de';
    case English = 'en';

    public static function normalize(?string $language, ?string $default = null): string
    {
        $candidate = $language ?? $default;
        $candidate = strtolower(trim((string) $candidate));

        if ($candidate === '') {
            return self::German->value;
        }

        if (str_starts_with($candidate, self::English->value)) {
            return self::English->value;
        }

        return self::German->value;
    }
}
