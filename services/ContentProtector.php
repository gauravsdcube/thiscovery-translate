<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

/**
 * Protects markup, URLs, emails, mentions, placeholders before Amazon Translate.
 * Uses plain ASCII placeholders (ZZTTnZZ) that survive text-mode translation.
 */
class ContentProtector
{
    /** @var array<string, string> */
    private array $map = [];

    private int $seq = 0;

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
        $text = $this->protectPattern($text, '/<[a-zA-Z][^>]*>/');
        $text = $this->protectPattern($text, '/<\/[a-zA-Z][^>]*>/');
        return $text;
    }

    public function hasTokens(): bool
    {
        return $this->map !== [];
    }

    public function restore(string $text): string
    {
        $keys = array_keys($this->map);
        usort($keys, static fn($a, $b) => strlen($b) <=> strlen($a));
        foreach ($keys as $token) {
            $text = str_ireplace($token, $this->map[$token], $text);
        }
        return $text;
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
        // Deliberately odd ASCII — Amazon text mode leaves these alone.
        $token = 'ZZTT' . $this->seq++ . 'ZZ';
        $this->map[$token] = $original;
        return $token;
    }
}
