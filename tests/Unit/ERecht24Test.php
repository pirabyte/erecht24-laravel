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

it('falls back to the documented eRecht24 demo plugin key', function () {
    $this->app['config']->set('erecht24.plugin_key', null);

    $client = new FakeLegalTextClient([
        LegalTextType::Imprint->value => legalTextFor(LegalTextType::Imprint),
    ]);
    $service = makeERecht24Service($client);

    $service->imprint();

    expect($client->pluginKeys)->toBe([ERecht24::DEMO_PLUGIN_KEY]);
});

it('can disable the documented eRecht24 demo plugin key fallback', function () {
    $this->app['config']->set('erecht24.plugin_key', null);
    $this->app['config']->set('erecht24.use_demo_plugin_key', false);

    $client = new FakeLegalTextClient([
        LegalTextType::Imprint->value => legalTextFor(LegalTextType::Imprint),
    ]);
    $service = makeERecht24Service($client);

    $service->imprint();

    expect($client->pluginKeys)->toBe([null]);
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
        ->and($this->app['cache']->store()->get('erecht24:imprint:de'))->toMatchArray([
            'type' => LegalTextType::Imprint->value,
            'html' => '<p>Deutsch</p>',
            'language' => 'de',
        ]);
});

it('migrates legacy cached objects to scalar payloads', function () {
    useSerializedArrayCache();

    $this->app['cache']->store()->put(
        'erecht24:imprint:de',
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

    $legacyObjectUnserializesSafely = $this->app['cache']->store()->get('erecht24:imprint:de') instanceof LegalTextData;

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
        ->and($this->app['cache']->store()->get('erecht24:imprint:de'))->toMatchArray([
            'type' => LegalTextType::Imprint->value,
            'html' => $expectedHtml,
            'language' => 'de',
        ]);
});

it('refreshes invalid cache entries', function () {
    useSerializedArrayCache();

    $this->app['cache']->store()->put('erecht24:imprint:de', 'invalid-cache-value', 3600);

    $client = new FakeLegalTextClient([
        LegalTextType::Imprint->value => legalTextFor(LegalTextType::Imprint),
    ]);
    $service = makeERecht24Service($client);

    $document = $service->imprint();

    expect($document)
        ->toBeInstanceOf(LegalTextData::class)
        ->and($document->html)->toBe('<p>Deutsch</p>')
        ->and($client->calls)->toBe(1)
        ->and($this->app['cache']->store()->get('erecht24:imprint:de'))->toMatchArray([
            'type' => LegalTextType::Imprint->value,
            'html' => '<p>Deutsch</p>',
            'language' => 'de',
        ]);
});

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

        return $this->documents[$type->value] ?? legalTextFor($type);
    }
}
