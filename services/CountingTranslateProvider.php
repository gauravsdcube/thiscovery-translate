<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

/**
 * Test double — counts Amazon invocations.
 */
class CountingTranslateProvider implements TranslateProviderInterface
{
    public int $calls = 0;

    public function translate(string $text, string $sourceLanguage, string $targetLanguage, string $format = 'text'): string
    {
        $this->calls++;
        return '[' . $targetLanguage . ']' . $text;
    }

    public function supportedLanguages(): array
    {
        return ['en' => 'English', 'fr' => 'French', 'cy' => 'Welsh'];
    }
}
