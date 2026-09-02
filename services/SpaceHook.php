<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

use humhub\modules\space\models\Space;
use humhub\modules\thiscoveryTranslate\models\ModuleSettings;
use Yii;

/**
 * Translates Space display fields (name, description, about, tags) without mutating stored DB rows.
 * Callers should use decorateForDisplay() for widgets that read $space->name directly.
 */
class SpaceHook
{
    private static ?ContentTranslateService $svc = null;

    public static function enabled(): bool
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

    /**
     * Clone a Space with translated display attributes (not saved; oldAttributes reset so not dirty).
     */
    public static function decorateForDisplay(Space $space): Space
    {
        if (!self::enabled() || empty($space->id)) {
            return $space;
        }
        try {
            $clone = clone $space;
            $name = self::translateName($space);
            $description = self::translateDescription($space);
            $about = self::translateAbout($space);
            if ($name !== '') {
                $clone->name = $name;
            }
            $clone->description = $description;
            if ($about !== '') {
                $clone->about = $about;
            }
            // Avoid accidental saves treating clones as dirty.
            $clone->setOldAttributes($clone->getAttributes());
            return $clone;
        } catch (\Throwable $e) {
            Yii::warning('SpaceHook decorate failed: ' . $e->getMessage(), 'thiscovery-translate');
            return $space;
        }
    }

    public static function translateName(Space $space): string
    {
        return self::field($space, 'name', (string)$space->name);
    }

    public static function translateDescription(Space $space): string
    {
        return self::field($space, 'description', (string)$space->description);
    }

    public static function translateAbout(Space $space): string
    {
        $about = trim((string)$space->about);
        if ($about === '') {
            return '';
        }
        return self::field($space, 'about', $about);
    }

    public static function translateTag(string $tag, int $spaceId = 0): string
    {
        $tag = trim($tag);
        if ($tag === '' || !self::enabled()) {
            return $tag;
        }
        try {
            return self::svc()->translateField(
                'space_tag',
                $spaceId > 0 ? $spaceId : 'global',
                'tag.' . md5(mb_strtolower($tag)),
                $tag,
                'space',
                'space'
            );
        } catch (\Throwable $e) {
            return $tag;
        }
    }

    /**
     * @param list<string> $tags
     * @return list<string>
     */
    public static function translateTags(array $tags, int $spaceId = 0): array
    {
        $out = [];
        foreach ($tags as $tag) {
            $label = is_object($tag) && isset($tag->name) ? (string)$tag->name : trim((string)$tag);
            if ($label === '') {
                continue;
            }
            $out[] = self::translateTag($label, $spaceId);
        }
        return $out;
    }

    public static function translateTopicName(int $topicId, string $name): string
    {
        $name = trim($name);
        if ($name === '' || !self::enabled() || $topicId < 1) {
            return $name;
        }
        try {
            return self::svc()->translateField('topic', $topicId, 'name', $name, 'space', 'topic');
        } catch (\Throwable $e) {
            return $name;
        }
    }

    private static function field(Space $space, string $field, string $text): string
    {
        $text = (string)$text;
        if (trim($text) === '' || !self::enabled() || empty($space->id)) {
            return $text;
        }
        try {
            return self::svc()->translateField(
                'space',
                (int)$space->id,
                $field,
                $text,
                'space',
                'space'
            );
        } catch (\Throwable $e) {
            Yii::warning('SpaceHook field failed: ' . $e->getMessage(), 'thiscovery-translate');
            return $text;
        }
    }

    private static function svc(): ContentTranslateService
    {
        if (self::$svc === null) {
            self::$svc = new ContentTranslateService();
        }
        return self::$svc;
    }
}
