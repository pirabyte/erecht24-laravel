<?php

namespace Pirabyte\ERecht24Laravel\Data;

use Pirabyte\ERecht24Laravel\Enums\LegalTextType;

final readonly class LegalTextData
{
    public function __construct(
        public LegalTextType $type,
        public ?string $html,
        public ?string $htmlDe,
        public ?string $htmlEn,
        public ?string $warnings,
        public ?string $createdAt,
        public ?string $modifiedAt,
        public ?string $pushedAt,
        public string $language,
    ) {}
}
