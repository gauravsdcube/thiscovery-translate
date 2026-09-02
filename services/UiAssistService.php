<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

use humhub\modules\thiscoveryTranslate\models\ModuleSettings;
use humhub\modules\thiscoveryTranslate\models\TranslationMemoryEntry;
use Yii;

/**
 * Lightweight UI-chrome translation used by beforeTranslateCallback.
 * Request-local TM map → Redis → Amazon (strict per-request budget). Never storms the page.
 */
class UiAssistService
{
    /** Max Amazon Translate calls per HTTP request for UI assist. */
    public const REQUEST_AMAZON_BUDGET = 8;

    private static int $amazonUsed = 0;

    /** @var array<string, array<string, string>> langPair => [sourceText => translated] */
    private static array $tmMaps = [];

    private ModuleSettings $settings;
    private UiStringCache $cache;
    private TranslationMemoryService $memory;
    private AmazonTranslateProvider $provider;

    public function __construct(?ModuleSettings $settings = null)
    {
        $this->settings = $settings ?: ModuleSettings::loadSettings();
        $this->cache = new UiStringCache();
        $this->memory = new TranslationMemoryService();
        $this->provider = new AmazonTranslateProvider($this->settings);
    }

    public function translate(string $text, string $targetLanguage, string $sourceLanguage): string
    {
        $text = trim($text);
        if ($text === '' || LocaleMap::sameLanguage($sourceLanguage, $targetLanguage)) {
            return $text;
        }

        $cached = $this->cache->get($text, $sourceLanguage, $targetLanguage, 'navigation');
        if ($cached !== null) {
            return $cached;
        }

        $peek = $this->peekMemory($text, $targetLanguage, $sourceLanguage);
        if ($peek !== null && $peek !== '') {
            $this->cache->set($text, $peek, $sourceLanguage, $targetLanguage, 'navigation');
            return $peek;
        }

        if (self::$amazonUsed >= self::REQUEST_AMAZON_BUDGET || !$this->settings->siteTranslateEnabled) {
            return $text;
        }
        if (mb_strlen($text) < 2 || mb_strlen($text) > 60) {
            return $text;
        }

        try {
            self::$amazonUsed++;
            $sourceAmz = LocaleMap::toAmazon($sourceLanguage);
            $targetAmz = LocaleMap::toAmazon($targetLanguage);
            $protector = new ContentProtector();
            $protected = $protector->protect($text);
            $out = $this->provider->translate($protected, $sourceAmz, $targetAmz, 'text');
            $out = $protector->restore($out);
            if (trim($out) === '') {
                return $text;
            }
            $this->memory->remember($text, $out, $sourceLanguage, $targetLanguage, 'navigation', 'amazon');
            $this->cache->set($text, $out, $sourceLanguage, $targetLanguage, 'navigation');
            $pair = $sourceAmz . '>' . $targetAmz;
            self::$tmMaps[$pair][$text] = $out;
            (new CostTracker())->record(
                'amazon',
                false,
                mb_strlen($text),
                $sourceLanguage,
                $targetLanguage,
                'amazon',
                'translate',
                'thiscovery-translate',
                'ui'
            );
            return $out;
        } catch (\Throwable $e) {
            Yii::warning('UI assist Amazon failed: ' . $e->getMessage(), 'thiscovery-translate');
            return $text;
        }
    }

    /** TM / request-map peek without calling Amazon. */
    public function peekMemory(string $text, string $targetLanguage, string $sourceLanguage): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $map = $this->tmMap($sourceLanguage, $targetLanguage);
        return isset($map[$text]) && $map[$text] !== '' ? $map[$text] : null;
    }

    /**
     * Load navigation TM for a language pair once per request (avoids N+1).
     * @return array<string, string>
     */
    private function tmMap(string $sourceLanguage, string $targetLanguage): array
    {
        $sourceAmz = LocaleMap::toAmazon($sourceLanguage);
        $targetAmz = LocaleMap::toAmazon($targetLanguage);
        $pair = $sourceAmz . '>' . $targetAmz;
        if (isset(self::$tmMaps[$pair])) {
            return self::$tmMaps[$pair];
        }
        $map = [];
        try {
            $rows = TranslationMemoryEntry::find()
                ->select(['source_text', 'translated_text'])
                ->where([
                    'source_language' => $sourceAmz,
                    'target_language' => $targetAmz,
                    'context' => 'navigation',
                ])
                ->asArray()
                ->all();
            foreach ($rows as $row) {
                $src = trim((string)$row['source_text']);
                $dst = trim((string)$row['translated_text']);
                if ($src !== '' && $dst !== '') {
                    $map[$src] = $dst;
                }
            }
        } catch (\Throwable $e) {
            $map = [];
        }
        self::$tmMaps[$pair] = $map;
        return $map;
    }

    /**
     * @param string[] $phrases
     * @return array{translated:int,skipped:int,failed:int}
     */
    public function warm(array $phrases, string $targetLanguage, string $sourceLanguage): array
    {
        $stats = ['translated' => 0, 'skipped' => 0, 'failed' => 0];
        if (LocaleMap::sameLanguage($sourceLanguage, $targetLanguage)) {
            return $stats;
        }
        $map = $this->tmMap($sourceLanguage, $targetLanguage);
        $seen = [];
        foreach ($phrases as $phrase) {
            $phrase = trim((string)$phrase);
            if ($phrase === '' || mb_strlen($phrase) > 120 || isset($seen[$phrase])) {
                $stats['skipped']++;
                continue;
            }
            $seen[$phrase] = true;
            if ($this->cache->get($phrase, $sourceLanguage, $targetLanguage, 'navigation') !== null || isset($map[$phrase])) {
                $stats['skipped']++;
                continue;
            }
            try {
                $sourceAmz = LocaleMap::toAmazon($sourceLanguage);
                $targetAmz = LocaleMap::toAmazon($targetLanguage);
                $out = $this->provider->translate($phrase, $sourceAmz, $targetAmz, 'text');
                if (trim($out) === '') {
                    $stats['failed']++;
                    continue;
                }
                $this->memory->remember($phrase, $out, $sourceLanguage, $targetLanguage, 'navigation', 'amazon');
                $this->cache->set($phrase, $out, $sourceLanguage, $targetLanguage, 'navigation');
                $pair = $sourceAmz . '>' . $targetAmz;
                self::$tmMaps[$pair][$phrase] = $out;
                (new CostTracker())->record(
                    'amazon',
                    false,
                    mb_strlen($phrase),
                    $sourceLanguage,
                    $targetLanguage,
                    'amazon',
                    'translate',
                    'thiscovery-translate',
                    'ui-warm'
                );
                $stats['translated']++;
            } catch (\Throwable $e) {
                $stats['failed']++;
                Yii::warning('UI warm failed: ' . $e->getMessage(), 'thiscovery-translate');
            }
        }
        return $stats;
    }

    /** @return string[] */
    public static function seedPhrases(): array
    {
        return [
            'Dashboard', 'People', 'Spaces', 'Space', 'Profile', 'Account', 'Administration', 'Admin',
            'Settings', 'Logout', 'Log out', 'Login', 'Sign in', 'Sign up', 'Register', 'Home',
            'Search', 'Notifications', 'Messages', 'Mail', 'Inbox', 'Directory', 'Members', 'About',
            'Stream', 'Latest posts', 'Follow', 'Unfollow', 'Like', 'Unlike', 'Comment', 'Share',
            'Edit', 'Delete', 'Save', 'Cancel', 'Close', 'Back', 'Next', 'Previous', 'Continue',
            'Submit', 'Send', 'Create', 'Add', 'Remove', 'Upload', 'Download', 'Filter', 'Sort',
            'More', 'Less', 'View', 'Open', 'Yes', 'No', 'OK', 'Confirm', 'Loading...', 'Loading',
            'Error', 'Success', 'Warning', 'Info', 'Help', 'Privacy', 'Terms', 'Contact',
            'My profile', 'My spaces', 'User Administration', 'Modules', 'Users', 'Groups',
            'Welcome', 'Get started', 'See all', 'Show more', 'Show less', 'Read more',
            'Post', 'Posts', 'Comments', 'File', 'Files', 'Calendar', 'Tasks',
            'Forms', 'Surveys', 'Projects', 'Panel', 'Resources', 'Events', 'Discussions',
            'Thiscovery', 'Menu', 'Language', 'English', 'Welsh', 'French', 'Hindi', 'Punjabi',
            // Page Builder chrome
            'Thiscovery Page Builder', 'Edit page', 'Moderate comments', 'Subscriptions',
            'Get involved', 'Get Involved', 'Public link', 'Meet the team', 'Available files',
            'Project phases', 'Get updates', 'Open files', 'Folder', 'Files', 'Subscribe',
            'Email address', 'Completed', 'Current', 'Upcoming', 'No form selected',
            'Form unavailable', 'Take the survey', 'Leave your email to hear about progress on this engagement.',
            'Thanks — we will keep you updated.', 'No files to show.', 'Nothing to show yet.',
            'Open Space', 'Sandbox stream', 'Inform', 'Have your say', 'What happens next',
            'Team / contacts', 'Phases', 'Space files', 'Documents',
        ];
    }
}
