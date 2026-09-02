<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

/**
 * Maps HumHub / Forms locale codes to Amazon Translate language codes.
 * Catalogue mirrors Amazon Translate supported languages (ISO 639-1 / RFC 5646).
 *
 * @see https://docs.aws.amazon.com/translate/latest/dg/what-is-languages.html
 */
class LocaleMap
{
    /**
     * Admin / Forms picker: HumHub locale => [amazonCode, English label]
     * Includes en-GB / en-US as English variants (both map to Amazon `en`).
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function catalog(): array
    {
        return [
            'af' => ['af', 'Afrikaans'],
            'sq' => ['sq', 'Albanian'],
            'am' => ['am', 'Amharic'],
            'ar' => ['ar', 'Arabic'],
            'hy' => ['hy', 'Armenian'],
            'az' => ['az', 'Azerbaijani'],
            'bn' => ['bn', 'Bengali'],
            'bs' => ['bs', 'Bosnian'],
            'bg' => ['bg', 'Bulgarian'],
            'ca' => ['ca', 'Catalan'],
            'zh' => ['zh', 'Chinese (Simplified)'],
            'zh-TW' => ['zh-TW', 'Chinese (Traditional)'],
            'hr' => ['hr', 'Croatian'],
            'cs' => ['cs', 'Czech'],
            'da' => ['da', 'Danish'],
            'fa-AF' => ['fa-AF', 'Dari'],
            'nl' => ['nl', 'Dutch'],
            'en-GB' => ['en', 'English (UK)'],
            'en-US' => ['en', 'English (US)'],
            'et' => ['et', 'Estonian'],
            'fa' => ['fa', 'Farsi (Persian)'],
            'tl' => ['tl', 'Filipino (Tagalog)'],
            'fi' => ['fi', 'Finnish'],
            'fr' => ['fr', 'French'],
            'fr-CA' => ['fr-CA', 'French (Canada)'],
            'ka' => ['ka', 'Georgian'],
            'de' => ['de', 'German'],
            'el' => ['el', 'Greek'],
            'gu' => ['gu', 'Gujarati'],
            'ht' => ['ht', 'Haitian Creole'],
            'ha' => ['ha', 'Hausa'],
            'he' => ['he', 'Hebrew'],
            'hi' => ['hi', 'Hindi'],
            'hu' => ['hu', 'Hungarian'],
            'is' => ['is', 'Icelandic'],
            'id' => ['id', 'Indonesian'],
            'ga' => ['ga', 'Irish'],
            'it' => ['it', 'Italian'],
            'ja' => ['ja', 'Japanese'],
            'kn' => ['kn', 'Kannada'],
            'kk' => ['kk', 'Kazakh'],
            'ko' => ['ko', 'Korean'],
            'lv' => ['lv', 'Latvian'],
            'lt' => ['lt', 'Lithuanian'],
            'mk' => ['mk', 'Macedonian'],
            'ms' => ['ms', 'Malay'],
            'ml' => ['ml', 'Malayalam'],
            'mt' => ['mt', 'Maltese'],
            'mr' => ['mr', 'Marathi'],
            'mn' => ['mn', 'Mongolian'],
            'no' => ['no', 'Norwegian (Bokmål)'],
            'ps' => ['ps', 'Pashto'],
            'pl' => ['pl', 'Polish'],
            'pt' => ['pt', 'Portuguese (Brazil)'],
            'pt-PT' => ['pt-PT', 'Portuguese (Portugal)'],
            'pa' => ['pa', 'Punjabi'],
            'ro' => ['ro', 'Romanian'],
            'ru' => ['ru', 'Russian'],
            'sr' => ['sr', 'Serbian'],
            'si' => ['si', 'Sinhala'],
            'sk' => ['sk', 'Slovak'],
            'sl' => ['sl', 'Slovenian'],
            'so' => ['so', 'Somali'],
            'es' => ['es', 'Spanish'],
            'es-MX' => ['es-MX', 'Spanish (Mexico)'],
            'sw' => ['sw', 'Swahili'],
            'sv' => ['sv', 'Swedish'],
            'ta' => ['ta', 'Tamil'],
            'te' => ['te', 'Telugu'],
            'th' => ['th', 'Thai'],
            'tr' => ['tr', 'Turkish'],
            'uk' => ['uk', 'Ukrainian'],
            'ur' => ['ur', 'Urdu'],
            'uz' => ['uz', 'Uzbek'],
            'vi' => ['vi', 'Vietnamese'],
            'cy' => ['cy', 'Welsh'],
        ];
    }

    /** @return array<string, string> */
    public static function nativeLabels(): array
    {
        return [
            'af' => 'Afrikaans',
            'sq' => 'Shqip',
            'am' => 'አማርኛ',
            'ar' => 'العربية',
            'hy' => 'Հայերեն',
            'az' => 'Azərbaycan',
            'bn' => 'বাংলা',
            'bs' => 'Bosanski',
            'bg' => 'Български',
            'ca' => 'Català',
            'zh' => '简体中文',
            'zh-TW' => '繁體中文',
            'hr' => 'Hrvatski',
            'cs' => 'Čeština',
            'da' => 'Dansk',
            'fa-AF' => 'دری',
            'nl' => 'Nederlands',
            'en-GB' => 'English (UK)',
            'en-US' => 'English (US)',
            'et' => 'Eesti',
            'fa' => 'فارسی',
            'tl' => 'Tagalog',
            'fi' => 'Suomi',
            'fr' => 'Français',
            'fr-CA' => 'Français (Canada)',
            'ka' => 'ქართული',
            'de' => 'Deutsch',
            'el' => 'Ελληνικά',
            'gu' => 'ગુજરાતી',
            'ht' => 'Kreyòl ayisyen',
            'ha' => 'Hausa',
            'he' => 'עברית',
            'hi' => 'हिन्दी',
            'hu' => 'Magyar',
            'is' => 'Íslenska',
            'id' => 'Bahasa Indonesia',
            'ga' => 'Gaeilge',
            'it' => 'Italiano',
            'ja' => '日本語',
            'kn' => 'ಕನ್ನಡ',
            'kk' => 'Қазақ',
            'ko' => '한국어',
            'lv' => 'Latviešu',
            'lt' => 'Lietuvių',
            'mk' => 'Македонски',
            'ms' => 'Bahasa Melayu',
            'ml' => 'മലയാളം',
            'mt' => 'Malti',
            'mr' => 'मराठी',
            'mn' => 'Монгол',
            'no' => 'Norsk bokmål',
            'ps' => 'پښتو',
            'pl' => 'Polski',
            'pt' => 'Português (Brasil)',
            'pt-PT' => 'Português (Portugal)',
            'pa' => 'ਪੰਜਾਬੀ',
            'ro' => 'Română',
            'ru' => 'Русский',
            'sr' => 'Српски',
            'si' => 'සිංහල',
            'sk' => 'Slovenčina',
            'sl' => 'Slovenščina',
            'so' => 'Soomaali',
            'es' => 'Español',
            'es-MX' => 'Español (México)',
            'sw' => 'Kiswahili',
            'sv' => 'Svenska',
            'ta' => 'தமிழ்',
            'te' => 'తెలుగు',
            'th' => 'ไทย',
            'tr' => 'Türkçe',
            'uk' => 'Українська',
            'ur' => 'اردو',
            'uz' => 'Oʻzbek',
            'vi' => 'Tiếng Việt',
            'cy' => 'Cymraeg',
        ];
    }

    public static function isRtl(string $locale): bool
    {
        $normalized = strtolower(str_replace('_', '-', trim($locale)));
        if (in_array($normalized, ['fa-af', 'he', 'ar', 'ur', 'fa', 'ps'], true)) {
            return true;
        }
        $base = explode('-', $normalized)[0] ?? '';
        return in_array($base, ['ar', 'ur', 'he', 'fa', 'ps', 'sd', 'yi', 'ug', 'dv'], true);
    }

    public static function labels(): array
    {
        $out = [];
        foreach (self::catalog() as $locale => [$code, $label]) {
            $out[$locale] = $label;
        }
        return $out;
    }

    public static function toAmazon(string $locale): string
    {
        $locale = str_replace('_', '-', trim($locale));
        $catalog = self::catalog();
        if (isset($catalog[$locale])) {
            return $catalog[$locale][0];
        }
        // Case-insensitive / base fallback
        foreach ($catalog as $key => $row) {
            if (strcasecmp($key, $locale) === 0) {
                return $row[0];
            }
        }
        $base = strtolower(explode('-', $locale)[0] ?? '');
        foreach ($catalog as $row) {
            if (strtolower($row[0]) === $base || strtolower(explode('-', $row[0])[0]) === $base) {
                return $row[0];
            }
        }
        return $base !== '' ? $base : 'en';
    }

    public static function sameLanguage(string $a, string $b): bool
    {
        return self::toAmazon($a) === self::toAmazon($b);
    }

    /**
     * @param string[] $locales
     * @return array<string, string> locale => label
     */
    public static function labelMapFor(array $locales): array
    {
        $all = self::labels();
        $out = [];
        foreach ($locales as $locale) {
            $out[$locale] = $all[$locale] ?? $locale;
        }
        return $out;
    }
}
