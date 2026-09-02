<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\commands;

use humhub\modules\thiscoveryTranslate\services\TranslationTransferService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\helpers\FileHelper;

/**
 * Export translation store / TM / terminology (and optionally form overlays) for migration between instances.
 *
 * Usage:
 *   php yii thiscovery-translate/export/data [path]
 *   php yii thiscovery-translate/export/data --forms=1
 *   php yii thiscovery-translate/export/data /tmp/out.json --forms=1
 */
class ExportController extends Controller
{
    /** @var int Include custom_form_i18n + custom_form_field_i18n when tables exist */
    public $forms = 0;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['forms']);
    }

    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), ['f' => 'forms']);
    }

    public function actionData(?string $path = null)
    {
        $stamp = date('Ymd-His');
        if ($path === null || $path === '') {
            $path = Yii::getAlias('@runtime/thiscovery-translate-export-' . $stamp . '.json');
        } else {
            $path = Yii::getAlias($path);
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            FileHelper::createDirectory($dir);
        }

        $service = new TranslationTransferService();
        $includeForms = (int)$this->forms === 1;

        try {
            $payload = $service->exportPayload($includeForms);
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if ($json === false) {
                throw new \RuntimeException('JSON encode failed: ' . json_last_error_msg());
            }
        } catch (\Throwable $e) {
            $this->stderr($e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if ($includeForms) {
            foreach (['custom_form_i18n', 'custom_form_field_i18n'] as $table) {
                if (Yii::$app->db->getTableSchema($table, true) === null) {
                    $this->stdout("skip {$table} (table missing)\n", Console::FG_YELLOW);
                }
            }
        }

        if (file_put_contents($path, $json) === false) {
            $this->stderr("Failed to write {$path}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $bytes = filesize($path) ?: strlen($json);
        $this->stdout("Wrote {$path} ({$bytes} bytes)\n", Console::FG_GREEN);
        foreach (['translations', 'translation_memory', 'terminology'] as $k) {
            $this->stdout('  ' . $k . '=' . count($payload[$k] ?? []) . "\n");
        }
        if (!empty($payload['forms']) && is_array($payload['forms'])) {
            foreach ($payload['forms'] as $table => $rows) {
                $this->stdout('  ' . $table . '=' . count($rows) . "\n");
            }
        }
        $this->stdout('  settings=' . count($payload['settings'] ?? []) . " (non-secret)\n");
        $this->stdout("Note: usage table is never exported. object_id values must match target instance objects; TM rows reuse by source_hash.\n");

        return ExitCode::OK;
    }
}
