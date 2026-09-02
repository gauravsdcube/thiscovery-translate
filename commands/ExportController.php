<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\commands;

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

        $db = Yii::$app->db;
        $tables = [
            'thiscovery_translation' => 'translations',
            'thiscovery_translation_memory' => 'translation_memory',
            'thiscovery_translation_terminology' => 'terminology',
        ];

        $payload = [
            'metadata' => [
                'format' => 'thiscovery-translate-export',
                'version' => '2.1.0',
                'exported_at' => gmdate('c'),
                'source_host' => gethostname() ?: (Yii::$app->request->hostName ?? ''),
                'module_version' => $this->moduleVersion(),
            ],
            'translations' => [],
            'translation_memory' => [],
            'terminology' => [],
            'settings' => $this->safeSettings(),
        ];

        $counts = [];
        foreach ($tables as $table => $key) {
            $rows = $db->createCommand('SELECT * FROM {{%' . $table . '}}')->queryAll();
            $payload[$key] = $rows;
            $counts[$key] = count($rows);
        }

        if ((int)$this->forms === 1) {
            $payload['forms'] = [];
            foreach (['custom_form_i18n', 'custom_form_field_i18n'] as $table) {
                if ($db->getTableSchema($table, true) === null) {
                    $this->stdout("skip {$table} (table missing)\n", Console::FG_YELLOW);
                    $payload['forms'][$table] = [];
                    $counts[$table] = 0;
                    continue;
                }
                $rows = $db->createCommand('SELECT * FROM {{%' . $table . '}}')->queryAll();
                $payload['forms'][$table] = $rows;
                $counts[$table] = count($rows);
            }
            $payload['metadata']['includes_forms'] = true;
        } else {
            $payload['metadata']['includes_forms'] = false;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            $this->stderr('JSON encode failed: ' . json_last_error_msg() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (file_put_contents($path, $json) === false) {
            $this->stderr("Failed to write {$path}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $bytes = filesize($path) ?: strlen($json);
        $this->stdout("Wrote {$path} ({$bytes} bytes)\n", Console::FG_GREEN);
        foreach ($counts as $k => $n) {
            $this->stdout("  {$k}={$n}\n");
        }
        $this->stdout('  settings=' . count($payload['settings']) . " (non-secret)\n");
        $this->stdout("Note: usage table is never exported. object_id values must match target instance objects; TM rows reuse by source_hash.\n");

        return ExitCode::OK;
    }

    private function moduleVersion(): string
    {
        $file = Yii::getAlias('@thiscovery-translate/module.json');
        if (is_file($file)) {
            $data = json_decode((string)file_get_contents($file), true);
            if (is_array($data) && !empty($data['version'])) {
                return (string)$data['version'];
            }
        }
        return '2.1.0';
    }

    /**
     * Export module settings excluding keys that look like secrets.
     * @return array<string, mixed>
     */
    private function safeSettings(): array
    {
        $module = Yii::$app->getModule('thiscovery-translate', false);
        if (!$module) {
            return [];
        }
        $out = [];
        try {
            $rows = Yii::$app->db->createCommand(
                'SELECT [[name]], [[value]] FROM {{%setting}} WHERE [[module_id]] = :m',
                [':m' => 'thiscovery-translate']
            )->queryAll();
        } catch (\Throwable $e) {
            $this->stderr('settings read: ' . $e->getMessage() . "\n", Console::FG_YELLOW);
            return [];
        }
        foreach ($rows as $row) {
            $name = (string)($row['name'] ?? '');
            if ($name === '' || $this->isSecretSettingName($name)) {
                continue;
            }
            $out[$name] = $row['value'];
        }
        return $out;
    }

    private function isSecretSettingName(string $name): bool
    {
        $n = strtolower($name);
        foreach (['secret', 'password', 'passwd', 'token', 'api_key', 'apikey', 'access_key', 'private_key', 'credential'] as $needle) {
            if (str_contains($n, $needle)) {
                return true;
            }
        }
        // bare "key" as whole segment (aws_access_key_id etc. already caught; avoid filtering aws_region)
        if (preg_match('/(^|_)key($|_)/', $n) && !str_contains($n, 'region')) {
            return true;
        }
        return false;
    }
}
