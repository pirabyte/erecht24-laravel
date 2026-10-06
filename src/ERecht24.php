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
use Pirabyte\ERecht24Laravel\Exceptions\ERecht24Exception;
use Pirabyte\ERecht24Laravel\Exceptions\MissingApiKeyException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class ERecht24
{
    public const DEVELOPER_KEY = 'nJ85RSm5HARYP4poy3bXip6VZQsxoEe1EvXQ36nuYU68fNhepigKBFUDreW1Z9iG';

    private const MAX_HTML_BYTES = 1_048_576;

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

        if (! $this->cacheEnabled()) {
            return $this->fetchDocument($type, $language, $apiKey);
        }

        $cache = $this->cacheRepository();
        $cacheKey = $this->cacheKey($type, $language);
        $cachedDocument = $this->cachedDocument($cache, $cacheKey, $type, $language);

        if ($cachedDocument instanceof LegalTextData) {
            return $cachedDocument;
        }

        $document = $this->fetchDocument($type, $language, $apiKey);

        $cache->put($cacheKey, $this->toCachePayload($document), $this->cacheTtl());

        return $document;
    }

    public function html(LegalTextType|string $type, ?string $language = null): ?string
    {
        return $this->document($type, $language)->html;
    }

    public function htmlOrLastKnownGood(LegalTextType|string $type, ?string $language = null): string
    {
        $type = LegalTextType::fromValue($type);
        $language = Language::normalize($language, $this->defaultLanguage());
        $this->apiKeyOrFail();
        $cacheKey = $this->cacheKey($type, $language).':last-known-good';

        try {
            $document = $this->document($type, $language);
            $html = $this->sanitizeHtml($language === Language::English->value ? $document->htmlEn : $document->htmlDe);

            if (! $this->isUsableHtml($html)) {
                throw new ERecht24Exception('The requested eRecht24 legal text is unavailable.');
            }

            if ($this->cacheEnabled()) {
                $this->cacheRepository()->forever($cacheKey, $html);
            }

            return $html;
        } catch (ERecht24Exception $exception) {
            if ($this->cacheEnabled()) {
                $html = $this->sanitizeHtml($this->cacheRepository()->get($cacheKey));

                if ($this->isUsableHtml($html)) {
                    return $html;
                }
            }

            throw $exception;
        }
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
                $cacheKey = $this->cacheKey($legalTextType, $language->value);
                $this->cacheRepository()->forget($cacheKey);
                $this->cacheRepository()->forget($cacheKey.':last-known-good');
            }
        }
    }

    private function isUsableHtml(string $html): bool
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_match('/[^\s\p{Z}\p{Cf}]/u', $text) === 1;
    }

    private function sanitizeHtml(mixed $html): string
    {
        if (! is_string($html) || strlen($html) > self::MAX_HTML_BYTES) {
            return '';
        }

        $config = (new HtmlSanitizerConfig)
            ->allowElement('a', ['href', 'name', 'rel', 'target', 'title'])
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(self::MAX_HTML_BYTES);

        foreach (['b', 'blockquote', 'br', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'i', 'li', 'ol', 'p', 'strong', 'ul'] as $element) {
            $config = $config->allowElement($element);
        }

        foreach (['article', 'div', 'section', 'span'] as $element) {
            $config = $config->blockElement($element);
        }

        return (new HtmlSanitizer($config))->sanitize($html);
    }

    private function fetchDocument(
        LegalTextType $type,
        string $language,
        string $apiKey,
    ): LegalTextData {
        return $this->toData(
            $type,
            $this->client->get($type, $apiKey, self::DEVELOPER_KEY),
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

    private function cachedDocument(
        CacheRepository $cache,
        string $cacheKey,
        LegalTextType $type,
        string $language,
    ): ?LegalTextData {
        $cached = $cache->get($cacheKey);
        $document = is_array($cached) ? $this->fromCachePayload($cached) : $cached;

        if ($document instanceof LegalTextData && $document->type === $type && $document->language === $language) {
            if ($cached instanceof LegalTextData) {
                $cache->put($cacheKey, $this->toCachePayload($document), $this->cacheTtl());
            }

            return $document;
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

        $project = hash('sha256', $this->apiKey() ?? '');

        return "{$prefix}:{$project}:{$type->value}:{$language}";
    }
}
