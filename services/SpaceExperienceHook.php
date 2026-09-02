<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

use humhub\modules\space\models\Space;
use Yii;

/**
 * Translates Thiscovery Space Experience welcome/overview copy for display.
 */
class SpaceExperienceHook
{
    private static ?ContentTranslateService $svc = null;

    public static function enabled(): bool
    {
        return SpaceHook::enabled();
    }

    /**
     * @param array{title?:string,content?:string,actions?:array} $welcome
     * @return array{title:string,content:string,actions:array}
     */
    public static function translateWelcome(Space $space, array $welcome): array
    {
        if (!self::enabled() || empty($space->id)) {
            return $welcome;
        }
        try {
            $spaceId = (int)$space->id;
            $title = trim((string)($welcome['title'] ?? ''));
            $content = (string)($welcome['content'] ?? '');
            $actions = is_array($welcome['actions'] ?? null) ? $welcome['actions'] : [];

            // Titles that already went through Yii::t (emoji + catalog) still benefit from field store
            // when they are custom DB welcome_title strings.
            if ($title !== '' && !str_starts_with($title, '👋 ')) {
                $title = self::field($spaceId, 'welcome_title', $title);
            } elseif ($title !== '') {
                // Default "Welcome to {space}" — translate space name portion via SpaceHook; UI assist covers Yii::t.
                $title = preg_replace_callback(
                    '/👋\s*(.+)/u',
                    static function (array $m) use ($space): string {
                        $rest = $m[1];
                        $name = $space->getDisplayName();
                        $translatedName = SpaceHook::translateName($space);
                        if ($translatedName !== '' && $translatedName !== $name && str_contains($rest, $name)) {
                            $rest = str_replace($name, $translatedName, $rest);
                        }
                        return '👋 ' . $rest;
                    },
                    $title
                ) ?: $title;
            }

            if (trim(strip_tags($content)) !== '') {
                $content = self::field($spaceId, 'welcome_content', $content);
            }

            foreach ($actions as $i => $action) {
                if (!is_array($action)) {
                    continue;
                }
                $label = trim((string)($action['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                // Short chrome via NavigationHook path (UiAssist) — stable key per space+index
                $actions[$i]['label'] = NavigationHook::translateLabel(
                    'space_experience.' . $spaceId . '.action.' . $i,
                    $label
                );
            }

            return [
                'title' => $title,
                'content' => $content,
                'actions' => $actions,
            ];
        } catch (\Throwable $e) {
            Yii::warning('SpaceExperienceHook welcome failed: ' . $e->getMessage(), 'thiscovery-translate');
            return $welcome;
        }
    }

    public static function translateText(Space $space, string $field, string $text): string
    {
        if (!self::enabled() || empty($space->id) || trim($text) === '') {
            return $text;
        }
        try {
            return self::field((int)$space->id, $field, $text);
        } catch (\Throwable $e) {
            return $text;
        }
    }

    /**
     * Translate stream highlight title/excerpt for a known content record.
     */
    public static function translateHighlight(?object $record, string $text): string
    {
        $text = trim($text);
        if ($text === '' || !self::enabled() || $record === null) {
            return $text;
        }
        try {
            return self::svc()->translateForDisplay($text, $record, 'message');
        } catch (\Throwable $e) {
            return $text;
        }
    }

    public static function translateResourceTitle(string $kind, int|string $id, string $title): string
    {
        $title = trim($title);
        if ($title === '' || !self::enabled()) {
            return $title;
        }
        try {
            return self::svc()->translateField(
                'space_resource',
                $kind . ':' . $id,
                'title',
                $title,
                'space',
                'space_experience'
            );
        } catch (\Throwable $e) {
            return $title;
        }
    }

    public static function translateResourceDescription(string $kind, int|string $id, ?string $description): ?string
    {
        if ($description === null) {
            return null;
        }
        $description = trim($description);
        if ($description === '' || !self::enabled()) {
            return $description;
        }
        try {
            return self::svc()->translateField(
                'space_resource',
                $kind . ':' . $id,
                'description',
                $description,
                'space',
                'space_experience'
            );
        } catch (\Throwable $e) {
            return $description;
        }
    }

    private static function field(int $spaceId, string $field, string $text): string
    {
        return self::svc()->translateField(
            'space_experience',
            $spaceId,
            $field,
            $text,
            'space',
            'space_experience'
        );
    }

    private static function svc(): ContentTranslateService
    {
        if (self::$svc === null) {
            self::$svc = new ContentTranslateService();
        }
        return self::$svc;
    }
}
