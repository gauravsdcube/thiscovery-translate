<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

use humhub\modules\thiscoveryTranslate\models\ModuleSettings;

/**
 * Amazon Translate provider (SigV4 + IAM role).
 */
class AmazonTranslateProvider implements TranslateProviderInterface
{
    private AwsTranslateClient $client;

    public function __construct(?ModuleSettings $settings = null)
    {
        $this->client = new AwsTranslateClient($settings);
    }

    public function translate(string $text, string $sourceAmazonCode, string $targetAmazonCode, string $format = 'text'): string
    {
        return $this->client->translateText($text, $sourceAmazonCode, $targetAmazonCode, $format);
    }

    public function supportedLanguages(): array
    {
        // Subset aligned with LocaleMap catalog amazon codes
        $out = [];
        foreach (LocaleMap::catalog() as $row) {
            $out[$row[0]] = $row[1];
        }
        return $out;
    }
}
