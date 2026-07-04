<?php

use Pirabyte\ERecht24Laravel\Data\LegalTextData;
use Pirabyte\ERecht24Laravel\Enums\LegalTextType;

it('stores legal text values', function () {
    $data = new LegalTextData(
        type: LegalTextType::PrivacyPolicy,
        html: '<p>Privacy</p>',
        htmlDe: '<p>Datenschutz</p>',
        htmlEn: '<p>Privacy</p>',
        warnings: 'Warnings',
        createdAt: '2026-01-01',
        modifiedAt: '2026-01-02',
        pushedAt: '2026-01-03',
        language: 'en',
    );

    expect($data->type)
        ->toBe(LegalTextType::PrivacyPolicy)
        ->and($data->html)->toBe('<p>Privacy</p>')
        ->and($data->htmlDe)->toBe('<p>Datenschutz</p>')
        ->and($data->htmlEn)->toBe('<p>Privacy</p>')
        ->and($data->warnings)->toBe('Warnings')
        ->and($data->createdAt)->toBe('2026-01-01')
        ->and($data->modifiedAt)->toBe('2026-01-02')
        ->and($data->pushedAt)->toBe('2026-01-03')
        ->and($data->language)->toBe('en');
});
