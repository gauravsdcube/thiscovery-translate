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

/**
 * Import translation export JSON into the three store tables (upsert by unique indexes).
 *
 * Usage:
 *   php yii thiscovery-translate/import/data path/to/file.json
 *   php yii thiscovery-translate/import/data path/to/file.json --settings=1
 *
 * Does not import usage. Form overlays are imported when present in the file and tables exist.
 */
class ImportController extends Controller
{
    /** @var int Merge non-secret module settings from the export */
    public $settings = 0;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['settings']);
    }

    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), ['s' => 'settings']);
    }

    public function actionData(string $path)
    {
        $path = Yii::getAlias($path);
        if (!is_file($path) || !is_readable($path)) {
            $this->stderr("File not found or unreadable: {$path}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $raw = file_get_contents($path);
        $data = json_decode((string)$raw, true);
        if (!is_array($data)) {
            $this->stderr('Invalid JSON: ' . json_last_error_msg() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $meta = $data['metadata'] ?? [];
        $this->stdout('Import from format=' . ($meta['format'] ?? '?')
            . ' version=' . ($meta['version'] ?? '?')
            . ' exported_at=' . ($meta['exported_at'] ?? '?')
            . ' host=' . ($meta['source_host'] ?? '?') . "\n");

        $service = new TranslationTransferService();
        try {
            $counts = $service->importPayload($data, (int)$this->settings === 1);
        } catch (\Throwable $e) {
            $this->stderr($e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Imported (upserted):\n", Console::FG_GREEN);
        foreach ($counts as $k => $n) {
            $this->stdout("  {$k}={$n}\n");
        }
        $this->stdout("Usage table was not imported. Caveat: translation rows key on object_id — IDs must match on the target; TM rows match by source_hash and are reusable across instances.\n");

        return ExitCode::OK;
    }
}
