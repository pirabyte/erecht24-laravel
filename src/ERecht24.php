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

        return $this->cacheRepository()->remember(
            $this->cacheKey($type, $language),
            $this->cacheTtl(),
            fn (): LegalTextData => $this->fetchDocument($type, $language, $apiKey, $pluginKey),
        );
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
        $pluginKey = $this->config->get('erecht24.plugin_key');

        if (! is_string($pluginKey)) {
            return null;
        }

        $pluginKey = trim($pluginKey);

        return $pluginKey === '' ? null : $pluginKey;
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
