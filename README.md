# eRecht24 Laravel

Laravel integration for the `erecht24/rechtstexte-sdk` package.

This package is a thin service layer around the official SDK. It fetches supported legal texts and returns DTOs so your application can decide how to render, cache, store, or publish them.

## Installation

```bash
composer require pirabyte/erecht24-laravel
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=erecht24-config
```

## Configuration

Set the required API key:

```dotenv
ERECHT24_API_KEY=
```

Optional configuration:

```dotenv
# Only set this if eRecht24 provided a plugin key for your integration.
ERECHT24_PLUGIN_KEY=
ERECHT24_LANGUAGE=de
ERECHT24_CACHE_ENABLED=true
ERECHT24_CACHE_TTL=3600
```

`ERECHT24_PLUGIN_KEY` may be left unset when you only have an API key. `ERECHT24_CACHE_STORE` and `ERECHT24_CACHE_PREFIX` are also available in the published config.

## Usage

Use the service directly:

```php
use Pirabyte\ERecht24Laravel\ERecht24;
use Pirabyte\ERecht24Laravel\Enums\LegalTextType;

$data = app(ERecht24::class)->document(LegalTextType::PrivacyPolicy, 'de');

echo $data->html;
```

Or use the facade:

```php
use Pirabyte\ERecht24Laravel\Facades\ERecht24;

$html = ERecht24::privacyPolicy()->html;
```

Available service methods:

```php
$erecht24->imprint();
$erecht24->privacyPolicy();
$erecht24->privacyPolicySocialMedia();
$erecht24->document(LegalTextType::Imprint);
$erecht24->html(LegalTextType::PrivacyPolicy);
$erecht24->isConfigured();
$erecht24->clearCache();
```

## Supported Documents

Only document types supported by `erecht24/rechtstexte-sdk` are exposed:

- `imprint`
- `privacy_policy`
- `privacy_policy_social_media`

Terms of Service documents are not supported by this package because the SDK does not expose them.

## Caching

Successful responses are cached when `erecht24.cache.enabled` is true. Cache keys use this format:

```text
erecht24:privacy_policy:de
```

Use `clearCache()` to forget all supported document cache keys, or pass a `LegalTextType` to clear a single document type.

## Disclaimer

Pirabyte is not affiliated with, endorsed by, or sponsored by eRecht24. eRecht24 is a trademark of its respective owner.

This package is technical integration software and is not legal advice. Users are responsible for validating their legal texts and their eRecht24 account/API setup.

## License

This wrapper package is open-sourced software licensed under the MIT license.

It depends on `erecht24/rechtstexte-sdk`, which has its own license and terms.
