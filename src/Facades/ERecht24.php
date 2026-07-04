<?php

namespace Pirabyte\ERecht24Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Pirabyte\ERecht24Laravel\Data\LegalTextData imprint(?string $language = null)
 * @method static \Pirabyte\ERecht24Laravel\Data\LegalTextData privacyPolicy(?string $language = null)
 * @method static \Pirabyte\ERecht24Laravel\Data\LegalTextData privacyPolicySocialMedia(?string $language = null)
 * @method static \Pirabyte\ERecht24Laravel\Data\LegalTextData document(\Pirabyte\ERecht24Laravel\Enums\LegalTextType|string $type, ?string $language = null)
 * @method static string|null html(\Pirabyte\ERecht24Laravel\Enums\LegalTextType|string $type, ?string $language = null)
 * @method static bool isConfigured()
 * @method static void clearCache(\Pirabyte\ERecht24Laravel\Enums\LegalTextType|string|null $type = null)
 *
 * @see \Pirabyte\ERecht24Laravel\ERecht24
 */
class ERecht24 extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'erecht24';
    }
}
