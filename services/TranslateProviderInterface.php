<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

/**
 * Contract for external translation providers.
 */
interface TranslateProviderInterface
{
    /**
     * @param 'text'|'html' $format
     */
    public function translate(string $text, string $sourceAmazonCode, string $targetAmazonCode, string $format = 'text'): string;

    /**
     * @return array<string, string> amazonCode => English label
     */
    public function supportedLanguages(): array;
}
