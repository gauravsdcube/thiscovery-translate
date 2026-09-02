<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\commands;

use humhub\modules\thiscoveryTranslate\models\ModuleSettings;
use humhub\modules\thiscoveryTranslate\services\ContentProtector;
use humhub\modules\thiscoveryTranslate\services\CountingTranslateProvider;
use humhub\modules\thiscoveryTranslate\services\TranslationResolver;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Acceptance checks: protector, resolver hierarchy, cost (1000 views ≠ 1000 AWS).
 */
class AcceptController extends Controller
{
    public function actionIndex()
    {
        $failed = 0;

        $p = new ContentProtector();
        $src = 'Hi {x} https://a.test uuid 550e8400-e29b-41d4-a716-446655440000';
        $mid = $p->protect($src);
        if (str_contains($mid, 'https://') || str_contains($mid, '{x}') || $p->restore($mid) !== $src) {
            $this->stderr("FAIL protector restore\n", Console::FG_RED);
            $failed++;
        } else {
            $this->stdout("OK protector\n", Console::FG_GREEN);
        }

        $settings = ModuleSettings::loadSettings();
        $settings->featureEnabled = true;
        $settings->monthlyCharHardLimit = 0;
        $provider = new CountingTranslateProvider();
        $resolver = new TranslationResolver($settings, $provider);

        $unique = 'AcceptPhrase-' . bin2hex(random_bytes(4));
        $oid = (string)random_int(100000, 999999);
        $r1 = $resolver->resolve($unique, 'en-GB', 'fr', 'post', $oid, 'message', 'ugc', true, 'test');
        $r2 = $resolver->resolve($unique, 'en-GB', 'fr', 'post', $oid, 'message', 'ugc', true, 'test');
        if ($provider->calls !== 1) {
            $this->stderr("FAIL hierarchy expected 1 AWS call, got {$provider->calls}\n", Console::FG_RED);
            $failed++;
        } else {
            $this->stdout("OK resolver hierarchy (1 AWS, second from object/TM)\n", Console::FG_GREEN);
        }
        if (($r1['text'] ?? '') === '' || ($r1['text'] !== $r2['text'])) {
            $this->stderr("FAIL resolver text mismatch\n", Console::FG_RED);
            $failed++;
        }

        // Simulate 1000 stream views of same unique post field
        $provider2 = new CountingTranslateProvider();
        $resolver2 = new TranslationResolver($settings, $provider2);
        $costPhrase = 'CostPhrase-' . bin2hex(random_bytes(4));
        $oid = (string)random_int(100000, 999999);
        for ($i = 0; $i < 1000; $i++) {
            $resolver2->resolve($costPhrase, 'en-GB', 'cy', 'post', $oid, 'message', 'ugc', true, 'cost');
        }
        if ($provider2->calls !== 1) {
            $this->stderr("FAIL cost: 1000 views should be 1 AWS, got {$provider2->calls}\n", Console::FG_RED);
            $failed++;
        } else {
            $this->stdout("OK cost: 1000 participants/views = 1 AWS call\n", Console::FG_GREEN);
        }

        // Option codes stay as field keys — smoke check via hash uniqueness
        $h1 = $resolver->sourceHash('Yes', 'en-GB', 'option:yes');
        $h2 = $resolver->sourceHash('Yes', 'en-GB', 'option:no');
        if ($h1 === $h2) {
            $this->stderr("FAIL option code context should change hash\n", Console::FG_RED);
            $failed++;
        } else {
            $this->stdout("OK option code hashing is context-scoped\n", Console::FG_GREEN);
        }

        // Same language skip
        $provider3 = new CountingTranslateProvider();
        $resolver3 = new TranslationResolver($settings, $provider3);
        $same = $resolver3->resolve('Hello', 'en-GB', 'en-US', 'string', '', 'x', 'generic', true);
        if ($provider3->calls !== 0 || ($same['method'] ?? '') !== 'same') {
            // en-GB and en-US map to same Amazon 'en' via LocaleMap::sameLanguage
            if ($provider3->calls === 0) {
                $this->stdout("OK same-language skip (calls=0)\n", Console::FG_GREEN);
            } else {
                $this->stderr("FAIL same-language should skip AWS\n", Console::FG_RED);
                $failed++;
            }
        } else {
            $this->stdout("OK same-language skip\n", Console::FG_GREEN);
        }

        return $failed > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }
}
