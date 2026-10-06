<?php

use eRecht24\RechtstexteSDK\ApiHandler;
use eRecht24\RechtstexteSDK\Exceptions\Exception as SdkException;
use eRecht24\RechtstexteSDK\Model\LegalText;
use eRecht24\RechtstexteSDK\Model\LegalText\Imprint;
use eRecht24\RechtstexteSDK\Model\LegalText\PrivacyPolicy;
use eRecht24\RechtstexteSDK\Model\LegalText\PrivacyPolicySocialMedia;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Pirabyte\ERecht24Laravel\Contracts\LegalTextClient;
use Pirabyte\ERecht24Laravel\Data\LegalTextData;
use Pirabyte\ERecht24Laravel\Enums\LegalTextType;
use Pirabyte\ERecht24Laravel\ERecht24;
use Pirabyte\ERecht24Laravel\Exceptions\ERecht24Exception;
use Pirabyte\ERecht24Laravel\Exceptions\MissingApiKeyException;
use Pirabyte\ERecht24Laravel\Exceptions\UnsupportedLegalTextTypeException;
use Pirabyte\ERecht24Laravel\SdkLegalTextClient;
use Pirabyte\ERecht24Laravel\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->app['config']->set('erecht24.api_key', 'api-key');
    $this->app['config']->set('erecht24.plugin_key', 'plugin-key');
    $this->app['config']->set('erecht24.language', 'de');
    $this->app['config']->set('erecht24.cache.enabled', false);
    $this->app['cache']->store()->flush();
});

it('reports whether the package is configured', function () {
    $service = makeERecht24Service(new FakeLegalTextClient);

    $this->app['config']->set('erecht24.api_key', null);

    expect($service->isConfigured())->toBeFalse();

    $this->app['config']->set('erecht24.api_key', 'api-key');

    expect($service->isConfigured())->toBeTrue();
});

it('throws when the API key is missing', function () {
    $this->app['config']->set('erecht24.api_key', null);

    $service = makeERecht24Service(new FakeLegalTextClient);

    expect(fn () => $service->imprint())->toThrow(MissingApiKeyException::class);
});

it('maps imprint SDK text into legal text data', function () {
    $service = makeERecht24Service(new FakeLegalTextClient([
        LegalTextType::Imprint->value => legalTextFor(LegalTextType::Imprint),
    ]));

    $data = $service->imprint();

    expect($data)
        ->toBeInstanceOf(LegalTextData::class)
        ->and($data->type)->toBe(LegalTextType::Imprint)
        ->and($data->html)->toBe('<p>Deutsch</p>')
        ->and($data->htmlDe)->toBe('<p>Deutsch</p>')
        ->and($data->htmlEn)->toBe('<p>English</p>')
        ->and($data->warnings)->toBe('Warnings')
        ->and($data->createdAt)->toBe('2026-01-01')
        ->and($data->modifiedAt)->toBe('2026-01-02')
        ->and($data->pushedAt)->toBe('2026-01-03')
        ->and($data->language)->toBe('de');
});

it('maps privacy policy SDK text into legal text data', function () {
    $service = makeERecht24Service(new FakeLegalTextClient([
        LegalTextType::PrivacyPolicy->value => legalTextFor(LegalTextType::PrivacyPolicy),
    ]));

    $data = $service->privacyPolicy();

    expect($data->type)
        ->toBe(LegalTextType::PrivacyPolicy)
        ->and($data->html)->toBe('<p>Deutsch</p>');
});

it('maps privacy policy social media SDK text into legal text data', function () {
    $service = makeERecht24Service(new FakeLegalTextClient([
        LegalTextType::PrivacyPolicySocialMedia->value => legalTextFor(LegalTextType::PrivacyPolicySocialMedia),
    ]));

    $data = $service->privacyPolicySocialMedia();

    expect($data->type)
        ->toBe(LegalTextType::PrivacyPolicySocialMedia)
        ->and($data->html)->toBe('<p>Deutsch</p>');
});

it('selects requested English and German HTML', function () {
    $service = makeERecht24Service(new FakeLegalTextClient([
        LegalTextType::PrivacyPolicy->value => legalTextFor(LegalTextType::PrivacyPolicy),
    ]));

    expect($service->document(LegalTextType::PrivacyPolicy, 'en')->html)
        ->toBe('<p>English</p>')
        ->and($service->document(LegalTextType::PrivacyPolicy, 'de')->html)
        ->toBe('<p>Deutsch</p>');
});

it('falls back to German HTML for an empty requested language', function () {
    $this->app['config']->set('erecht24.language', 'en');

    $service = makeERecht24Service(new FakeLegalTextClient([
        LegalTextType::PrivacyPolicy->value => legalTextFor(LegalTextType::PrivacyPolicy),
    ]));

    expect($service->document(LegalTextType::PrivacyPolicy, '')->html)
        ->toBe('<p>Deutsch</p>');
});

it('throws for unsupported document types', function () {
    $service = makeERecht24Service(new FakeLegalTextClient);

    expect(fn () => $service->document('terms'))->toThrow(UnsupportedLegalTextTypeException::class);
});

it('uses the configured plugin key when one is set', function () {
    $client = new FakeLegalTextClient([
        LegalTextType::Imprint->value => legalTextFor(LegalTextType::Imprint),
    ]);
    $service = makeERecht24Service($client);

    $service->imprint();

    expect($client->pluginKeys)->toBe(['plugin-key']);
});

it('uses the configured plugin key fallback from config', function () {
    $this->app['config']->set('erecht24.plugin_key', 'vRuG4GQHxYb9MkxU3HURJTyDUHyDyE3scTV4vzzR8VPHbwyT3krWzM6vS4vmeqfm');

    $client = new FakeLegalTextClient([
        LegalTextType::Imprint->value => legalTextFor(LegalTextType::Imprint),
    ]);
    $service = makeERecht24Service($client);

    $service->imprint();

    expect($client->pluginKeys)->toBe(['vRuG4GQHxYb9MkxU3HURJTyDUHyDyE3scTV4vzzR8VPHbwyT3krWzM6vS4vmeqfm']);
});

it('caches successful responses when cache is enabled', function () {
    $this->app['config']->set('erecht24.cache.enabled', true);

    $client = new FakeLegalTextClient([
        LegalTextType::Imprint->value => legalTextFor(LegalTextType::Imprint),
    ]);
    $service = makeERecht24Service($client);

    $service->imprint();
    $service->imprint();

    expect($client->calls)->toBe(1);
});

it('isolates cached legal texts and cache clearing between project keys', function () {
    $this->app['config']->set('erecht24.cache.enabled', true);

    $firstClient = new FakeLegalTextClient([
        LegalTextType::Imprint->value => new Imprint(['html_de' => '<p>First project</p>']),
    ]);
    $secondClient = new FakeLegalTextClient([
        LegalTextType::Imprint->value => new Imprint(['html_de' => '<p>Second project</p>']),
    ]);
    $firstService = makeERecht24Service($firstClient);
    $secondService = makeERecht24Service($secondClient);

    expect($firstService->imprint()->html)->toBe('<p>First project</p>');

    $this->app['config']->set('erecht24.api_key', 'second-project-key');

    expect($secondService->imprint()->html)->toBe('<p>Second project</p>');

    $secondService->clearCache();
    $this->app['config']->set('erecht24.api_key', 'api-key');

    expect($firstService->imprint()->html)->toBe('<p>First project</p>')
        ->and($firstClient->calls)->toBe(1);

    $this->app['config']->set('erecht24.api_key', 'second-project-key');

    expect($secondService->imprint()->html)->toBe('<p>Second project</p>')
        ->and($secondClient->calls)->toBe(2);
});

it('returns valid HTML in the exact requested language', function () {
    $service = makeERecht24Service(new FakeLegalTextClient);

    expect($service->htmlOrLastKnownGood('imprint', 'en'))->toBe('<p>English</p>')
        ->and($service->htmlOrLastKnownGood('imprint', 'de'))->toBe('<p>Deutsch</p>');
});

it('rejects missing or blank requested HTML without falling back to German', function (?string $html) {
    $client = new FakeLegalTextClient([
        'imprint' => new Imprint(['html_de' => '<p>Deutsch</p>', 'html_en' => $html]),
    ]);
    $service = makeERecht24Service($client);

    expect(fn () => $service->htmlOrLastKnownGood('imprint', 'en'))
        ->toThrow(ERecht24Exception::class, 'The requested eRecht24 legal text is unavailable.');
})->with([null, '', " \n ", '<p> </p>']);

it('retains last known good HTML after the fresh cache expires', function () {
    $this->app['config']->set('erecht24.cache.enabled', true);
    $this->app['config']->set('erecht24.cache.ttl', 1);
    $client = new FakeLegalTextClient;
    $service = makeERecht24Service($client);

    expect($service->htmlOrLastKnownGood('imprint', 'en'))->toBe('<p>English</p>');

    $this->travel(2)->seconds();
    $client->failRequests = true;

    expect($service->htmlOrLastKnownGood('imprint', 'en'))->toBe('<p>English</p>')
        ->and($client->calls)->toBe(2);

    $service->clearCache(LegalTextType::Imprint);

    expect(fn () => $service->htmlOrLastKnownGood('imprint', 'en'))
        ->toThrow(ERecht24Exception::class, 'Upstream unavailable.');
});

it('does not serve last known good HTML with caching disabled or another project key', function () {
    $this->app['config']->set('erecht24.cache.enabled', true);
    $this->app['config']->set('erecht24.cache.ttl', 1);
    $client = new FakeLegalTextClient;
    $service = makeERecht24Service($client);
    $service->htmlOrLastKnownGood('imprint', 'en');
    $this->travel(2)->seconds();
    $client->failRequests = true;
    $this->app['config']->set('erecht24.cache.enabled', false);

    expect(fn () => $service->htmlOrLastKnownGood('imprint', 'en'))
        ->toThrow(ERecht24Exception::class, 'Upstream unavailable.');

    $this->app['config']->set('erecht24.cache.enabled', true);
    $this->app['config']->set('erecht24.api_key', 'another-project');

    expect(fn () => $service->htmlOrLastKnownGood('imprint', 'en'))
        ->toThrow(ERecht24Exception::class, 'Upstream unavailable.');

    $this->app['config']->set('erecht24.api_key', null);

    expect(fn () => $service->htmlOrLastKnownGood('imprint', 'en'))
        ->toThrow(MissingApiKeyException::class);
});

it('preserves last known good HTML when refreshed content is unusable after sanitizing', function (string $html) {
    $this->app['config']->set('erecht24.cache.enabled', true);
    $this->app['config']->set('erecht24.cache.ttl', 1);
    makeERecht24Service(new FakeLegalTextClient)->htmlOrLastKnownGood('imprint', 'en');
    $this->travel(2)->seconds();
    $service = makeERecht24Service(new FakeLegalTextClient([
        'imprint' => new Imprint(['html_de' => '', 'html_en' => $html]),
    ]));

    expect($service->htmlOrLastKnownGood('imprint', 'en'))->toBe('<p>English</p>')
        ->and(fn () => $service->htmlOrLastKnownGood('imprint', 'de'))
        ->toThrow(ERecht24Exception::class);
})->with([
    'empty' => '',
    'script only' => '<script>alert(1)</script>',
    'style only' => '<style>body { display: none }</style>',
    'HTML whitespace entities' => '<p>&nbsp;&#160;</p>',
    'Unicode whitespace' => "<p>\u{2003}\u{200B}</p>",
    'oversized' => str_repeat('x', 1_048_577),
]);

it('sanitizes legal HTML before returning and retaining it while preserving raw document access', function () {
    $this->app['config']->set('erecht24.cache.enabled', true);
    $this->app['config']->set('erecht24.cache.ttl', 1);
    $unsafeHtml = '<div onclick="alert(1)"><h2>Legal notice</h2><script>alert(1)</script><style>body{display:none}</style><p style="color:red">Text <strong>important</strong></p><ul><li>Item</li></ul><img src="x" onerror="alert(1)"><a href="javascript:alert(1)" onclick="alert(1)">Unsafe</a><a href="https://example.com" target="_blank">Safe</a><a href="/contact">Contact</a><a href="mailto:hello@example.com">Email</a><a href="tel:+491234">Call</a></div>';
    $client = new FakeLegalTextClient([
        'imprint' => new Imprint(['html_en' => $unsafeHtml]),
    ]);
    $service = makeERecht24Service($client);
    $html = $service->htmlOrLastKnownGood('imprint', 'en');

    expect(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'))->toContain('<h2>Legal notice</h2>', '<p>Text <strong>important</strong></p>', '<ul><li>Item</li></ul>', 'href="https://example.com"', 'href="/contact"', 'href="mailto:hello@example.com"', 'href="tel:+491234"', 'rel="noopener noreferrer"')
        ->not->toContain('<script', '<style', '<img', 'onclick', 'onerror', 'javascript:', 'style=', 'alert(1)')
        ->and($service->imprint('en')->htmlEn)->toBe($unsafeHtml);

    $this->travel(2)->seconds();
    $client->failRequests = true;

    expect($service->htmlOrLastKnownGood('imprint', 'en'))->toBe($html);
});

it('sanitizes retained HTML again before serving it after an upstream failure', function () {
    $this->app['config']->set('erecht24.cache.enabled', true);
    $client = new FakeLegalTextClient;
    $client->failRequests = true;
    $service = makeERecht24Service($client);
    $cacheKey = 'erecht24:'.hash('sha256', 'api-key').':imprint:en:last-known-good';
    $this->app['cache']->store()->forever($cacheKey, '<p onclick="alert(1)">Retained</p><script>alert(1)</script>');

    expect($service->htmlOrLastKnownGood('imprint', 'en'))->toBe('<p>Retained</p>');

    $this->app['cache']->store()->forever($cacheKey, '<script>alert(1)</script>');

    expect(fn () => $service->htmlOrLastKnownGood('imprint', 'en'))
        ->toThrow(ERecht24Exception::class, 'Upstream unavailable.');
});

it('stores cache entries as scalar payloads for serialized cache stores', function () {
    useSerializedArrayCache();

    $client = new FakeLegalTextClient([
        LegalTextType::Imprint->value => legalTextFor(LegalTextType::Imprint),
    ]);
    $service = makeERecht24Service($client);

    $firstDocument = $service->imprint();
    $secondDocument = $service->imprint();

    expect($firstDocument)
        ->toBeInstanceOf(LegalTextData::class)
        ->and($secondDocument)->toBeInstanceOf(LegalTextData::class)
        ->and($secondDocument->html)->toBe('<p>Deutsch</p>')
        ->and($client->calls)->toBe(1)
        ->and($this->app['cache']->store()->get(imprintCacheKey()))->toMatchArray([
            'type' => LegalTextType::Imprint->value,
            'html' => '<p>Deutsch</p>',
            'language' => 'de',
        ]);
});

it('migrates legacy cached objects to scalar payloads', function () {
    useSerializedArrayCache();

    $this->app['cache']->store()->put(
        imprintCacheKey(),
        new LegalTextData(
            type: LegalTextType::Imprint,
            html: '<p>Legacy cached object.</p>',
            htmlDe: '<p>Legacy cached object.</p>',
            htmlEn: null,
            warnings: null,
            createdAt: null,
            modifiedAt: null,
            pushedAt: null,
            language: 'de',
        ),
        3600,
    );

    $legacyObjectUnserializesSafely = $this->app['cache']->store()->get(imprintCacheKey()) instanceof LegalTextData;

    $client = new FakeLegalTextClient([
        LegalTextType::Imprint->value => legalTextFor(LegalTextType::Imprint),
    ]);
    $service = makeERecht24Service($client);

    $document = $service->imprint();
    $expectedHtml = $legacyObjectUnserializesSafely ? '<p>Legacy cached object.</p>' : '<p>Deutsch</p>';
    $expectedCalls = $legacyObjectUnserializesSafely ? 0 : 1;

    expect($document)
        ->toBeInstanceOf(LegalTextData::class)
        ->and($document->html)->toBe($expectedHtml)
        ->and($client->calls)->toBe($expectedCalls)
        ->and($this->app['cache']->store()->get(imprintCacheKey()))->toMatchArray([
            'type' => LegalTextType::Imprint->value,
            'html' => $expectedHtml,
            'language' => 'de',
        ]);
});

it('refreshes invalid cache entries', function () {
    useSerializedArrayCache();

    $this->app['cache']->store()->put(imprintCacheKey(), 'invalid-cache-value', 3600);

    $client = new FakeLegalTextClient([
        LegalTextType::Imprint->value => legalTextFor(LegalTextType::Imprint),
    ]);
    $service = makeERecht24Service($client);

    $document = $service->imprint();

    expect($document)
        ->toBeInstanceOf(LegalTextData::class)
        ->and($document->html)->toBe('<p>Deutsch</p>')
        ->and($client->calls)->toBe(1)
        ->and($this->app['cache']->store()->get(imprintCacheKey()))->toMatchArray([
            'type' => LegalTextType::Imprint->value,
            'html' => '<p>Deutsch</p>',
            'language' => 'de',
        ]);
});

it('refreshes cached documents with a different type or language', function (string $type, string $language, bool $legacyObject) {
    useSerializedArrayCache();

    if ($legacyObject) {
        // Keep legacy DTOs readable to verify migration validation on every Laravel version.
        $this->app['config']->set('cache.stores.array.serialize', false);
        $this->app['cache']->forgetDriver('array');
    }

    $cachedDocument = new LegalTextData(
        type: LegalTextType::from($type),
        html: '<p>Wrong document</p>',
        htmlDe: '<p>Wrong document</p>',
        htmlEn: null,
        warnings: null,
        createdAt: null,
        modifiedAt: null,
        pushedAt: null,
        language: $language,
    );
    $cache = $this->app['cache']->store();
    $cache->put(imprintCacheKey(), $legacyObject ? $cachedDocument : [
        'type' => $type,
        'html' => $cachedDocument->html,
        'language' => $language,
    ], 3600);

    $client = new FakeLegalTextClient([
        LegalTextType::Imprint->value => legalTextFor(LegalTextType::Imprint),
    ]);
    $service = makeERecht24Service($client);

    $document = $service->imprint();
    $cachedAgain = $service->imprint();

    expect($document->type)->toBe(LegalTextType::Imprint)
        ->and($document->language)->toBe('de')
        ->and($document->html)->toBe('<p>Deutsch</p>')
        ->and($cachedAgain->html)->toBe('<p>Deutsch</p>')
        ->and($client->calls)->toBe(1)
        ->and($cache->get(imprintCacheKey()))->toMatchArray([
            'type' => LegalTextType::Imprint->value,
            'html' => '<p>Deutsch</p>',
            'language' => 'de',
        ]);
})->with([
    'payload with wrong document' => ['privacy_policy', 'de', false],
    'payload with wrong language' => ['imprint', 'en', false],
    'legacy object with wrong document' => ['privacy_policy', 'de', true],
    'legacy object with wrong language' => ['imprint', 'en', true],
]);

it('clears document-specific cache keys', function () {
    $this->app['config']->set('erecht24.cache.enabled', true);

    $client = new FakeLegalTextClient([
        LegalTextType::PrivacyPolicy->value => legalTextFor(LegalTextType::PrivacyPolicy),
    ]);
    $service = makeERecht24Service($client);

    $service->privacyPolicy();
    $service->clearCache(LegalTextType::PrivacyPolicy);
    $service->privacyPolicy();

    expect($client->calls)->toBe(2);
});

it('wraps SDK exceptions in package exceptions', function () {
    $client = new class extends SdkLegalTextClient
    {
        protected function makeApiHandler(string $apiKey, ?string $pluginKey): ApiHandler
        {
            throw new SdkException('SDK failed.');
        }
    };

    expect(fn () => $client->get(LegalTextType::Imprint, 'api-key', 'plugin-key'))
        ->toThrow(ERecht24Exception::class, 'SDK failed.');
});

function makeERecht24Service(LegalTextClient $client): ERecht24
{
    return new ERecht24(
        $client,
        app(ConfigRepository::class),
        app(CacheFactory::class),
    );
}

function useSerializedArrayCache(): void
{
    app('config')->set('erecht24.cache.enabled', true);
    app('config')->set('cache.default', 'array');
    app('config')->set('cache.stores.array.serialize', true);
    app('config')->set('cache.serializable_classes', false);
    app('cache')->forgetDriver('array');
    app('cache')->store()->flush();
}

function legalTextFor(LegalTextType $type): LegalText
{
    $attributes = [
        'html_de' => '<p>Deutsch</p>',
        'html_en' => '<p>English</p>',
        'warnings' => 'Warnings',
        'created' => '2026-01-01',
        'modified' => '2026-01-02',
        'pushed' => '2026-01-03',
    ];

    return match ($type) {
        LegalTextType::Imprint => new Imprint($attributes),
        LegalTextType::PrivacyPolicy => new PrivacyPolicy($attributes),
        LegalTextType::PrivacyPolicySocialMedia => new PrivacyPolicySocialMedia($attributes),
    };
}

final class FakeLegalTextClient implements LegalTextClient
{
    public int $calls = 0;

    public bool $failRequests = false;

    /**
     * @var array<int, string|null>
     */
    public array $pluginKeys = [];

    /**
     * @param  array<string, LegalText>  $documents
     */
    public function __construct(private readonly array $documents = []) {}

    public function get(LegalTextType $type, string $apiKey, ?string $pluginKey = null): LegalText
    {
        $this->calls++;
        $this->pluginKeys[] = $pluginKey;

        if ($this->failRequests) {
            throw new ERecht24Exception('Upstream unavailable.');
        }

        return $this->documents[$type->value] ?? legalTextFor($type);
    }
}

function imprintCacheKey(): string
{
    return 'erecht24:'.hash('sha256', 'api-key').':imprint:de';
}
