<?php

namespace Pirabyte\ERecht24Laravel\Contracts;

use eRecht24\RechtstexteSDK\Model\LegalText;
use Pirabyte\ERecht24Laravel\Enums\LegalTextType;
use Pirabyte\ERecht24Laravel\Exceptions\ERecht24Exception;

interface LegalTextClient
{
    /**
     * @throws ERecht24Exception
     */
    public function get(LegalTextType $type, string $apiKey, ?string $pluginKey = null): LegalText;
}
