<?php

namespace Pirabyte\ERecht24Laravel\Enums;

use Pirabyte\ERecht24Laravel\Exceptions\UnsupportedLegalTextTypeException;

enum LegalTextType: string
{
    case Imprint = 'imprint';
    case PrivacyPolicy = 'privacy_policy';
    case PrivacyPolicySocialMedia = 'privacy_policy_social_media';

    public static function fromValue(self|string $type): self
    {
        if ($type instanceof self) {
            return $type;
        }

        $normalizedType = trim($type);
        $legalTextType = self::tryFrom($normalizedType);

        if ($legalTextType === null) {
            throw UnsupportedLegalTextTypeException::forType($normalizedType);
        }

        return $legalTextType;
    }
}
