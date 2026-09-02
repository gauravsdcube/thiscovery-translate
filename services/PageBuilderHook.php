<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

use humhub\modules\thiscoveryTranslate\models\ModuleSettings;
use Yii;

/**
 * Lazy-translates Engagement Page Builder block settings for display.
 * Never writes back to sections_json — originals stay English/source.
 */
class PageBuilderHook
{
    /** Keys that are human-readable copy (not IDs, colours, enums, URLs). */
    private const TEXT_KEYS = [
        'title' => true,
        'intro' => true,
        'headline' => true,
        'subheadline' => true,
        'body' => true,
        'label' => true,
        'description' => true,
        'button_label' => true,
        'success_message' => true,
        'cta_label' => true,
        'empty_text' => true,
        'role' => true,
        'bio' => true,
        'text' => true,
        'caption' => true,
        'subtitle' => true,
        'content' => true,
        'placeholder' => true,
        'summary' => true,
    ];

    /** Skip these even if nested under text-like structures. */
    private const SKIP_KEYS = [
        'email' => true,
        'phone' => true,
        'name' => true,
        'url' => true,
        'href' => true,
        'slug' => true,
        'guid' => true,
        'file_guid' => true,
        'image_guid' => true,
        'form_id' => true,
        'poll_id' => true,
        'status' => true,
        'style' => true,
        'align' => true,
        'background_color' => true,
        'text_color' => true,
        'border_color' => true,
        'show_border' => true,
        'type' => true,
        'region' => true,
        'icon' => true,
        'target' => true,
    ];

    private static ?ContentTranslateService $svc = null;

    public static function translateBlockSettings($page, string $blockType, array $settings): array
    {
        if (!self::enabled()) {
            return $settings;
        }
        $pageId = is_object($page) && isset($page->id) ? (int)$page->id : 0;
        if ($pageId < 1) {
            return $settings;
        }
        try {
            return self::walk($settings, $pageId, $blockType, '');
        } catch (\Throwable $e) {
            Yii::warning('PageBuilderHook failed: ' . $e->getMessage(), 'thiscovery-translate');
            return $settings;
        }
    }

    /**
     * Translate page chrome (title, summary, category, top menu label) for display.
     *
     * @return array{title:string,summary:string,category:string,top_menu_label:string}
     */
    public static function translatePageMeta($page): array
    {
        $title = is_object($page) ? (string)($page->title ?? '') : '';
        $summary = is_object($page) ? (string)($page->summary ?? '') : '';
        $category = is_object($page) ? (string)($page->category ?? '') : '';
        $topLabel = is_object($page) ? (string)($page->top_menu_label ?? '') : '';
        $pageId = is_object($page) && isset($page->id) ? (int)$page->id : 0;

        if (!self::enabled() || $pageId < 1) {
            return [
                'title' => $title,
                'summary' => $summary,
                'category' => $category,
                'top_menu_label' => $topLabel,
            ];
        }
        try {
            if ($title !== '') {
                $title = self::svc()->translateField('engagement_page', $pageId, 'title', $title, 'page_builder');
            }
            if (trim(strip_tags($summary)) !== '') {
                $summary = self::svc()->translateField('engagement_page', $pageId, 'summary', $summary, 'page_builder');
            }
            if ($category !== '') {
                $category = self::svc()->translateField('engagement_page', $pageId, 'category', $category, 'page_builder');
            }
            if ($topLabel !== '') {
                $topLabel = NavigationHook::translateLabel('engagement_page.' . $pageId . '.top_menu', $topLabel);
            } elseif ($title !== '') {
                $topLabel = $title;
            }
            return [
                'title' => $title,
                'summary' => $summary,
                'category' => $category,
                'top_menu_label' => $topLabel,
            ];
        } catch (\Throwable $e) {
            Yii::warning('PageBuilderHook meta failed: ' . $e->getMessage(), 'thiscovery-translate');
            return [
                'title' => $title,
                'summary' => $summary,
                'category' => $category,
                'top_menu_label' => $topLabel !== '' ? $topLabel : $title,
            ];
        }
    }

    public static function translateTopMenuLabel($page): string
    {
        $meta = self::translatePageMeta($page);
        $label = trim($meta['top_menu_label'] ?? '');
        if ($label !== '') {
            return $label;
        }
        return trim($meta['title'] ?? '');
    }

    private static function enabled(): bool
    {
        try {
            $module = Yii::$app->getModule('thiscovery-translate');
            if ($module === null || !$module->getIsEnabled()) {
                return false;
            }
            $settings = ModuleSettings::loadSettings();
            return $settings->siteTranslateEnabled && $settings->contentTranslate;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function svc(): ContentTranslateService
    {
        if (self::$svc === null) {
            self::$svc = new ContentTranslateService();
        }
        return self::$svc;
    }

    /**
     * @param mixed $node
     * @return mixed
     */
    private static function walk($node, int $pageId, string $blockType, string $path)
    {
        if (!is_array($node)) {
            return $node;
        }
        $out = [];
        foreach ($node as $key => $value) {
            $keyStr = (string)$key;
            $childPath = $path === '' ? $keyStr : ($path . '.' . $keyStr);

            if (isset(self::SKIP_KEYS[$keyStr])) {
                $out[$key] = $value;
                continue;
            }

            if (is_string($value) && isset(self::TEXT_KEYS[$keyStr])) {
                $out[$key] = self::translateField($pageId, $blockType, $childPath, $value);
                continue;
            }

            if (is_array($value)) {
                $out[$key] = self::walk($value, $pageId, $blockType, $childPath);
                continue;
            }

            $out[$key] = $value;
        }
        return $out;
    }

    private static function translateField(int $pageId, string $blockType, string $path, string $text): string
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > 5000) {
            return $text;
        }
        // Skip pure codes / hex colours / bare numbers
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $text) || preg_match('/^\d+(\.\d+)?$/', $text)) {
            return $text;
        }
        // Skip empty richtext shells (e.g. <p class="te-p"><br></p>)
        $plain = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $plain = preg_replace('/\x{00a0}/u', ' ', $plain) ?? $plain;
        if (trim($plain) === '') {
            return $text;
        }

        return self::svc()->translateField(
            'engagement_page',
            $pageId,
            'block.' . $blockType . '.' . $path,
            $text,
            'page_builder'
        );
    }
}
