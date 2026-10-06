<?php

use eRecht24\RechtstexteSDK\ApiHandler;
use eRecht24\RechtstexteSDK\Model\LegalText\Imprint;
use eRecht24\RechtstexteSDK\Model\Response;
use Pirabyte\ERecht24Laravel\Enums\LegalTextType;
use Pirabyte\ERecht24Laravel\Exceptions\ERecht24Exception;
use Pirabyte\ERecht24Laravel\SdkLegalTextClient;

it('rejects unsuccessful SDK responses instead of returning an empty legal text', function (int $status) {
    $client = sdkClientWithResponse($status, new Imprint);

    try {
        $client->get(LegalTextType::Imprint, 'project-key', 'plugin-key');
        test()->fail('Expected an API exception.');
    } catch (ERecht24Exception $exception) {
        expect($exception->getCode())->toBe($status)
            ->and($exception->getMessage())->toBe('The eRecht24 API request failed.');
    }
})->with([0, 301, 401, 403, 429, 500]);

it('returns the legal text from successful SDK responses', function () {
    $document = new Imprint(['html_de' => '<p>Imprint</p>']);
    $client = sdkClientWithResponse(200, $document);

    expect($client->get(LegalTextType::Imprint, 'project-key', 'plugin-key'))
        ->toBe($document);
});

function sdkClientWithResponse(int $status, Imprint $document): SdkLegalTextClient
{
    $handler = Mockery::mock(ApiHandler::class);
    $handler->shouldReceive('getImprint')->once()->andReturn($document);
    $handler->shouldReceive('getResponse')->once()->andReturn(new Response([
        'code' => $status,
        'body' => '{}',
    ]));

    return new class($handler) extends SdkLegalTextClient
    {
        public function __construct(private readonly ApiHandler $handler) {}

        protected function makeApiHandler(string $apiKey, ?string $pluginKey): ApiHandler
        {
            return $this->handler;
        }
    };
}
