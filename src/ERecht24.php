<?php

namespace Pirabyte\ERecht24Laravel;

use eRecht24\RechtstexteSDK\Model\LegalText;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Pirabyte\ERecht24Laravel\Contracts\LegalTextClient;
use Pirabyte\ERecht24Laravel\Data\LegalTextData;
use Pirabyte\ERecht24Laravel\Enums\Language;
use Pirabyte\ERecht24Laravel\Enums\LegalTextType;
use Pirabyte\ERecht24Laravel\Exceptions\MissingApiKeyException;

class ERecht24
{
    public function __construct(
        private readonly LegalTextClient $client,
        private readonly ConfigRepository $config,
        private readonly CacheFactory $cache,
    ) {}

    public function imprint(?string $language = null): LegalTextData
    {
        return $this->document(LegalTextType::Imprint, $language);
    }

    public function privacyPolicy(?string $language = null): LegalTextData
    {
        return $this->document(LegalTextType::PrivacyPolicy, $language);
    }

    public function privacyPolicySocialMedia(?string $language = null): LegalTextData
    {
        return $this->document(LegalTextType::PrivacyPolicySocialMedia, $language);
    }

    public function document(LegalTextType|string $type, ?string $language = null): LegalTextData
    {
        $type = LegalTextType::fromValue($type);
        $language = Language::normalize($language, $this->defaultLanguage());
        $apiKey = $this->apiKeyOrFail();
        $pluginKey = $this->pluginKey();

        if (! $this->cacheEnabled()) {
            return $this->fetchDocument($type, $language, $apiKey, $pluginKey);
        }

        $cache = $this->cacheRepository();
        $cacheKey = $this->cacheKey($type, $language);
        $cachedDocument = $this->cachedDocument($cache, $cacheKey);

        if ($cachedDocument instanceof LegalTextData) {
            return $cachedDocument;
        }

        $document = $this->fetchDocument($type, $language, $apiKey, $pluginKey);

        $cache->put($cacheKey, $this->toCachePayload($document), $this->cacheTtl());

        return $document;
    }

    public function html(LegalTextType|string $type, ?string $language = null): ?string
    {
        return $this->document($type, $language)->html;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    public function clearCache(LegalTextType|string|null $type = null): void
    {
        $types = $type === null ? LegalTextType::cases() : [LegalTextType::fromValue($type)];

        foreach ($types as $legalTextType) {
            foreach (Language::cases() as $language) {
                $this->cacheRepository()->forget($this->cacheKey($legalTextType, $language->value));
            }
        }
    }

    private function fetchDocument(
        LegalTextType $type,
        string $language,
        string $apiKey,
        ?string $pluginKey,
    ): LegalTextData {
        return $this->toData(
            $type,
            $this->client->get($type, $apiKey, $pluginKey),
            $language,
        );
    }

    private function toData(LegalTextType $type, LegalText $legalText, string $language): LegalTextData
    {
        $htmlDe = $legalText->getHtmlDE();
        $htmlEn = $legalText->getHtmlEN();
        $html = $language === Language::English->value ? ($htmlEn ?: $htmlDe) : ($htmlDe ?: $htmlEn);

        return new LegalTextData(
            type: $type,
            html: $html,
            htmlDe: $htmlDe,
            htmlEn: $htmlEn,
            warnings: $legalText->getWarnings(),
            createdAt: $legalText->getCreatedAt(),
            modifiedAt: $legalText->getModifiedAt(),
            pushedAt: $legalText->getPushed(),
            language: $language,
        );
    }

    private function cachedDocument(CacheRepository $cache, string $cacheKey): ?LegalTextData
    {
        $cached = $cache->get($cacheKey);

        if (is_array($cached)) {
            $document = $this->fromCachePayload($cached);

            if ($document instanceof LegalTextData) {
                return $document;
            }
        }

        if ($cached instanceof LegalTextData) {
            $cache->put($cacheKey, $this->toCachePayload($cached), $this->cacheTtl());

            return $cached;
        }

        if ($cached !== null) {
            $cache->forget($cacheKey);
        }

        return null;
    }

    /**
     * @return array{
     *     type: string,
     *     html: string|null,
     *     html_de: string|null,
     *     html_en: string|null,
     *     warnings: string|null,
     *     created_at: string|null,
     *     modified_at: string|null,
     *     pushed_at: string|null,
     *     language: string
     * }
     */
    private function toCachePayload(LegalTextData $document): array
    {
        return [
            'type' => $document->type->value,
            'html' => $document->html,
            'html_de' => $document->htmlDe,
            'html_en' => $document->htmlEn,
            'warnings' => $document->warnings,
            'created_at' => $document->createdAt,
            'modified_at' => $document->modifiedAt,
            'pushed_at' => $document->pushedAt,
            'language' => $document->language,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fromCachePayload(array $payload): ?LegalTextData
    {
        $type = isset($payload['type']) && is_string($payload['type'])
            ? LegalTextType::tryFrom($payload['type'])
            : null;

        if (! $type instanceof LegalTextType || ! isset($payload['language']) || ! is_string($payload['language'])) {
            return null;
        }

        return new LegalTextData(
            type: $type,
            html: $this->nullableString($payload['html'] ?? null),
            htmlDe: $this->nullableString($payload['html_de'] ?? null),
            htmlEn: $this->nullableString($payload['html_en'] ?? null),
            warnings: $this->nullableString($payload['warnings'] ?? null),
            createdAt: $this->nullableString($payload['created_at'] ?? null),
            modifiedAt: $this->nullableString($payload['modified_at'] ?? null),
            pushedAt: $this->nullableString($payload['pushed_at'] ?? null),
            language: $payload['language'],
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function apiKeyOrFail(): string
    {
        return $this->apiKey() ?? throw MissingApiKeyException::make();
    }

    private function apiKey(): ?string
    {
        $apiKey = $this->config->get('erecht24.api_key');

        if (! is_string($apiKey)) {
            return null;
        }

        $apiKey = trim($apiKey);

        return $apiKey === '' ? null : $apiKey;
    }

    private function pluginKey(): ?string
    {
        return $this->configuredString('erecht24.plugin_key');
    }

    private function defaultLanguage(): string
    {
        $language = $this->config->get('erecht24.language', Language::German->value);

        return is_string($language) ? $language : Language::German->value;
    }

    private function cacheEnabled(): bool
    {
        return filter_var(
            $this->config->get('erecht24.cache.enabled', true),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    private function configuredString(string $key): ?string
    {
        $value = $this->config->get($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function cacheRepository(): CacheRepository
    {
        $store = $this->config->get('erecht24.cache.store');

        if (is_string($store) && trim($store) !== '') {
            return $this->cache->store($store);
        }

        return $this->cache->store();
    }

    private function cacheTtl(): int
    {
        $ttl = $this->config->get('erecht24.cache.ttl', 3600);

        if (! is_numeric($ttl)) {
            return 3600;
        }

        return (int) $ttl;
    }

    private function cacheKey(LegalTextType $type, string $language): string
    {
        $prefix = $this->config->get('erecht24.cache.prefix', 'erecht24');
        $prefix = is_string($prefix) && trim($prefix) !== '' ? trim($prefix, ':') : 'erecht24';

        return "{$prefix}:{$type->value}:{$language}";
    }
}
