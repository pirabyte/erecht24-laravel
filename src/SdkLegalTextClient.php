<?php

namespace Pirabyte\ERecht24Laravel;

use eRecht24\RechtstexteSDK\ApiHandler;
use eRecht24\RechtstexteSDK\Exceptions\Exception as SdkException;
use eRecht24\RechtstexteSDK\Model\LegalText;
use Pirabyte\ERecht24Laravel\Contracts\LegalTextClient;
use Pirabyte\ERecht24Laravel\Enums\LegalTextType;
use Pirabyte\ERecht24Laravel\Exceptions\ERecht24Exception;

class SdkLegalTextClient implements LegalTextClient
{
    public function get(LegalTextType $type, string $apiKey, ?string $pluginKey = null): LegalText
    {
        try {
            $apiHandler = $this->makeApiHandler($apiKey, $pluginKey);

            $document = match ($type) {
                LegalTextType::Imprint => $apiHandler->getImprint(),
                LegalTextType::PrivacyPolicy => $apiHandler->getPrivacyPolicy(),
                LegalTextType::PrivacyPolicySocialMedia => $apiHandler->getPrivacyPolicySocialMedia(),
            };

            $status = $apiHandler->getResponse()->getCode();

            if ($status === null || $status < 200 || $status >= 300) {
                throw new ERecht24Exception('The eRecht24 API request failed.', $status ?? 0);
            }

            return $document;
        } catch (SdkException $exception) {
            throw ERecht24Exception::fromThrowable($exception);
        }
    }

    /**
     * @throws SdkException
     */
    protected function makeApiHandler(string $apiKey, ?string $pluginKey): ApiHandler
    {
        return new ApiHandler($apiKey, $pluginKey);
    }
}
