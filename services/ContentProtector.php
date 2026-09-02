<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

/**
 * Protects markup, URLs, emails, mentions, placeholders before Amazon Translate.
 *
 * Uses empty <span translate="no" data-tth="N"></span> markers (HTML format).
 * Amazon leaves translate="no" spans alone; restore swaps them back from the map.
 * Legacy ZZTT…ZZ tokens are still restored when present in the map.
 */
class ContentProtector
{
    /** @var array<string, string> id => original */
    private array $map = [];

    private int $seq = 0;

    /** Pattern for leaked/mangled legacy ASCII tokens (and intact ones). */
    public const LEAK_PATTERN = '/ZZ?TT?\d+Z+|ZZTT\d+ZZ|<span\b[^>]*\bdata-tth=/i';

    public function protect(string $text): string
    {
        $this->map = [];
        $this->seq = 0;
        $text = $this->protectPattern($text, '/```[\s\S]*?```/');
        $text = $this->protectPattern($text, '/`[^`\n]+`/');
        $text = $this->protectPattern($text, '/\[[^\]]*\]\((?:mention|oembed|file|mailto):[^)]+\)/');
        $text = $this->protectPattern($text, '/\{\{[a-zA-Z0-9_.]+\}\}/');
        $text = $this->protectPattern($text, '/\{[a-zA-Z0-9_]+\}/');
        $text = $this->protectPattern($text, '/%[A-Z0-9_]+%/');
        $text = $this->protectPattern($text, '/\[[a-zA-Z0-9_]+\]/');
        $text = $this->protectPattern($text, '/https?:\/\/[^\s<>"\']+/i');
        $text = $this->protectPattern($text, '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/');
        $text = $this->protectPattern($text, '/@[a-zA-Z0-9_.\-]+/');
        $text = $this->protectPattern($text, '/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/');
        $text = $this->protectPattern($text, '/<\/?[a-zA-Z][^>]*>/');
        return $text;
    }

    public function hasTokens(): bool
    {
        return $this->map !== [];
    }

    public function restore(string $text): string
    {
        // New HTML markers (empty or with accidental inner text).
        $text = preg_replace_callback(
            '/<span\b[^>]*\bdata-tth=["\']?(\d+)["\']?[^>]*(?:\/>|>([\s\S]*?)<\/span>)/i',
            function ($m) {
                $id = (string)$m[1];
                return $this->map[$id] ?? ($m[2] ?? '');
            },
            $text
        ) ?? $text;

        // Legacy ASCII tokens (exact map keys first, longest first).
        $legacyKeys = [];
        foreach ($this->map as $id => $original) {
            $legacyKeys['ZZTT' . $id . 'ZZ'] = $original;
        }
        uksort($legacyKeys, static fn($a, $b) => strlen($b) <=> strlen($a));
        foreach ($legacyKeys as $token => $original) {
            $text = str_ireplace($token, $original, $text);
        }

        return $text;
    }

    /**
     * True when text still contains protector markers (failed restore / bad store).
     */
    public static function looksLeaked(string $text): bool
    {
        return $text !== '' && (bool)preg_match(self::LEAK_PATTERN, $text);
    }

    /**
     * @param string[] $terms
     */
    public function protectTerms(string $text, array $terms): string
    {
        usort($terms, static fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach ($terms as $term) {
            $term = trim((string)$term);
            if ($term === '') {
                continue;
            }
            $text = preg_replace_callback(
                '/' . preg_quote($term, '/') . '/u',
                function ($m) {
                    return $this->token($m[0]);
                },
                $text
            ) ?? $text;
        }
        return $text;
    }

    private function protectPattern(string $text, string $pattern): string
    {
        return preg_replace_callback($pattern, function ($m) {
            return $this->token($m[0]);
        }, $text) ?? $text;
    }

    private function token(string $original): string
    {
        $id = (string)$this->seq++;
        $this->map[$id] = $original;
        // Empty span — Amazon HTML mode keeps translate="no"; restore uses the map.
        return '<span translate="no" data-tth="' . $id . '"></span>';
    }
}
