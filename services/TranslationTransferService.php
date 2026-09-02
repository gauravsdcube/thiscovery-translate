<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

use Yii;
use yii\db\Connection;

/**
 * Shared export/import for translation store, TM, terminology, settings, and optional form overlays.
 */
class TranslationTransferService
{
    public const FORMAT = 'thiscovery-translate-export';
    public const FORMAT_VERSION = '2.1.0';

    /**
     * Full payload with metadata, translations, TM, terminology, settings, optional forms.
     *
     * @return array<string, mixed>
     */
    public function exportPayload(bool $includeForms = false): array
    {
        $db = Yii::$app->db;
        $tables = [
            'thiscovery_translation' => 'translations',
            'thiscovery_translation_memory' => 'translation_memory',
            'thiscovery_translation_terminology' => 'terminology',
        ];

        $payload = [
            'metadata' => [
                'format' => self::FORMAT,
                'version' => self::FORMAT_VERSION,
                'exported_at' => gmdate('c'),
                'source_host' => gethostname() ?: (Yii::$app->request->hostName ?? ''),
                'module_version' => $this->moduleVersion(),
                'includes_forms' => false,
            ],
            'translations' => [],
            'translation_memory' => [],
            'terminology' => [],
            'settings' => $this->safeSettings(),
        ];

        foreach ($tables as $table => $key) {
            $payload[$key] = $db->createCommand('SELECT * FROM {{%' . $table . '}}')->queryAll();
        }

        if ($includeForms) {
            $payload['forms'] = [];
            foreach (['custom_form_i18n', 'custom_form_field_i18n'] as $table) {
                if ($db->getTableSchema($table, true) === null) {
                    $payload['forms'][$table] = [];
                    continue;
                }
                $payload['forms'][$table] = $db->createCommand('SELECT * FROM {{%' . $table . '}}')->queryAll();
            }
            $payload['metadata']['includes_forms'] = true;
        }

        return $payload;
    }

    public function exportJson(bool $includeForms = false): string
    {
        $json = json_encode(
            $this->exportPayload($includeForms),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
        if ($json === false) {
            throw new \RuntimeException('JSON encode failed: ' . json_last_error_msg());
        }
        return $json;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, int> counts of upserted rows
     */
    public function importPayload(array $data, bool $mergeSettings = false): array
    {
        $db = Yii::$app->db;
        $counts = [];

        $counts['translations'] = $this->upsertTranslation(
            $db,
            $data['translations'] ?? $data['thiscovery_translation'] ?? []
        );
        $counts['translation_memory'] = $this->upsertMemory(
            $db,
            $data['translation_memory'] ?? $data['thiscovery_translation_memory'] ?? []
        );
        $counts['terminology'] = $this->upsertTerminology(
            $db,
            $data['terminology'] ?? $data['thiscovery_translation_terminology'] ?? []
        );

        $forms = $data['forms'] ?? null;
        if (is_array($forms)) {
            if (!empty($forms['custom_form_i18n']) && $db->getTableSchema('custom_form_i18n', true)) {
                $counts['custom_form_i18n'] = $this->upsertFormI18n($db, $forms['custom_form_i18n']);
            }
            if (!empty($forms['custom_form_field_i18n']) && $db->getTableSchema('custom_form_field_i18n', true)) {
                $counts['custom_form_field_i18n'] = $this->upsertFormFieldI18n($db, $forms['custom_form_field_i18n']);
            }
        }

        if ($mergeSettings && !empty($data['settings']) && is_array($data['settings'])) {
            $counts['settings'] = $this->mergeSettings($data['settings']);
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    public function importJson(string $json, bool $mergeSettings = false): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        return $this->importPayload($data, $mergeSettings);
    }

    public function moduleVersion(): string
    {
        $file = Yii::getAlias('@thiscovery-translate/module.json');
        if (is_file($file)) {
            $data = json_decode((string)file_get_contents($file), true);
            if (is_array($data) && !empty($data['version'])) {
                return (string)$data['version'];
            }
        }
        return self::FORMAT_VERSION;
    }

    /**
     * Export module settings excluding keys that look like secrets.
     * @return array<string, mixed>
     */
    public function safeSettings(): array
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

    public function isSecretSettingName(string $name): bool
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

    private function upsertTranslation(Connection $db, array $rows): int
    {
        $n = 0;
        $sql = 'INSERT INTO {{%thiscovery_translation}} (
            [[object_type]], [[object_id]], [[field]], [[source_language]], [[target_language]],
            [[source_text]], [[source_hash]], [[translated_text]], [[translation_method]], [[translation_status]],
            [[is_manual]], [[is_locked]], [[context]], [[terminology_version]],
            [[created_at]], [[updated_at]], [[translated_at]]
        ) VALUES (
            :object_type, :object_id, :field, :source_language, :target_language,
            :source_text, :source_hash, :translated_text, :translation_method, :translation_status,
            :is_manual, :is_locked, :context, :terminology_version,
            :created_at, :updated_at, :translated_at
        ) ON DUPLICATE KEY UPDATE
            [[source_language]]=VALUES([[source_language]]),
            [[source_text]]=VALUES([[source_text]]),
            [[translated_text]]=VALUES([[translated_text]]),
            [[translation_method]]=VALUES([[translation_method]]),
            [[translation_status]]=VALUES([[translation_status]]),
            [[is_manual]]=VALUES([[is_manual]]),
            [[is_locked]]=VALUES([[is_locked]]),
            [[context]]=VALUES([[context]]),
            [[terminology_version]]=VALUES([[terminology_version]]),
            [[updated_at]]=VALUES([[updated_at]]),
            [[translated_at]]=VALUES([[translated_at]])';

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $db->createCommand($sql, [
                ':object_type' => (string)($row['object_type'] ?? ''),
                ':object_id' => (string)($row['object_id'] ?? ''),
                ':field' => (string)($row['field'] ?? ''),
                ':source_language' => (string)($row['source_language'] ?? ''),
                ':target_language' => (string)($row['target_language'] ?? ''),
                ':source_text' => (string)($row['source_text'] ?? ''),
                ':source_hash' => (string)($row['source_hash'] ?? ''),
                ':translated_text' => (string)($row['translated_text'] ?? ''),
                ':translation_method' => (string)($row['translation_method'] ?? 'amazon'),
                ':translation_status' => (string)($row['translation_status'] ?? 'machine'),
                ':is_manual' => (int)(bool)($row['is_manual'] ?? 0),
                ':is_locked' => (int)(bool)($row['is_locked'] ?? 0),
                ':context' => $row['context'] ?? null,
                ':terminology_version' => (int)($row['terminology_version'] ?? 0),
                ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                ':updated_at' => $row['updated_at'] ?? date('Y-m-d H:i:s'),
                ':translated_at' => $row['translated_at'] ?? null,
            ])->execute();
            $n++;
        }
        return $n;
    }

    private function upsertMemory(Connection $db, array $rows): int
    {
        $n = 0;
        $sql = 'INSERT INTO {{%thiscovery_translation_memory}} (
            [[source_language]], [[target_language]], [[source_hash]], [[source_text]], [[translated_text]],
            [[context]], [[translation_method]], [[is_verified]], [[usage_count]], [[created_at]], [[updated_at]]
        ) VALUES (
            :source_language, :target_language, :source_hash, :source_text, :translated_text,
            :context, :translation_method, :is_verified, :usage_count, :created_at, :updated_at
        ) ON DUPLICATE KEY UPDATE
            [[source_text]]=VALUES([[source_text]]),
            [[translated_text]]=VALUES([[translated_text]]),
            [[translation_method]]=VALUES([[translation_method]]),
            [[is_verified]]=VALUES([[is_verified]]),
            [[usage_count]]=GREATEST([[usage_count]], VALUES([[usage_count]])),
            [[updated_at]]=VALUES([[updated_at]])';

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $db->createCommand($sql, [
                ':source_language' => (string)($row['source_language'] ?? ''),
                ':target_language' => (string)($row['target_language'] ?? ''),
                ':source_hash' => (string)($row['source_hash'] ?? ''),
                ':source_text' => (string)($row['source_text'] ?? ''),
                ':translated_text' => (string)($row['translated_text'] ?? ''),
                ':context' => (string)($row['context'] ?? 'generic'),
                ':translation_method' => (string)($row['translation_method'] ?? 'amazon'),
                ':is_verified' => (int)(bool)($row['is_verified'] ?? 0),
                ':usage_count' => (int)($row['usage_count'] ?? 0),
                ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                ':updated_at' => $row['updated_at'] ?? date('Y-m-d H:i:s'),
            ])->execute();
            $n++;
        }
        return $n;
    }

    private function upsertTerminology(Connection $db, array $rows): int
    {
        $n = 0;
        $sql = 'INSERT INTO {{%thiscovery_translation_terminology}} (
            [[source_term]], [[target_language]], [[preferred_translation]], [[do_not_translate]],
            [[description]], [[context]], [[is_active]], [[created_at]], [[updated_at]]
        ) VALUES (
            :source_term, :target_language, :preferred_translation, :do_not_translate,
            :description, :context, :is_active, :created_at, :updated_at
        ) ON DUPLICATE KEY UPDATE
            [[preferred_translation]]=VALUES([[preferred_translation]]),
            [[do_not_translate]]=VALUES([[do_not_translate]]),
            [[description]]=VALUES([[description]]),
            [[context]]=VALUES([[context]]),
            [[is_active]]=VALUES([[is_active]]),
            [[updated_at]]=VALUES([[updated_at]])';

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $db->createCommand($sql, [
                ':source_term' => (string)($row['source_term'] ?? ''),
                ':target_language' => (string)($row['target_language'] ?? '*'),
                ':preferred_translation' => $row['preferred_translation'] ?? null,
                ':do_not_translate' => (int)(bool)($row['do_not_translate'] ?? 0),
                ':description' => $row['description'] ?? null,
                ':context' => $row['context'] ?? null,
                ':is_active' => (int)(bool)($row['is_active'] ?? 1),
                ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                ':updated_at' => $row['updated_at'] ?? date('Y-m-d H:i:s'),
            ])->execute();
            $n++;
        }
        return $n;
    }

    private function upsertFormI18n(Connection $db, array $rows): int
    {
        $n = 0;
        $sql = 'INSERT INTO {{%custom_form_i18n}} (
            [[form_id]], [[language]], [[title]], [[description]], [[thank_you_content]]
        ) VALUES (
            :form_id, :language, :title, :description, :thank_you_content
        ) ON DUPLICATE KEY UPDATE
            [[title]]=VALUES([[title]]),
            [[description]]=VALUES([[description]]),
            [[thank_you_content]]=VALUES([[thank_you_content]])';

        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['form_id']) || empty($row['language'])) {
                continue;
            }
            // Skip if parent form missing (FK)
            $exists = $db->createCommand('SELECT 1 FROM {{%custom_form}} WHERE [[id]]=:id', [':id' => $row['form_id']])->queryScalar();
            if (!$exists) {
                continue;
            }
            $db->createCommand($sql, [
                ':form_id' => (int)$row['form_id'],
                ':language' => (string)$row['language'],
                ':title' => $row['title'] ?? null,
                ':description' => $row['description'] ?? null,
                ':thank_you_content' => $row['thank_you_content'] ?? null,
            ])->execute();
            $n++;
        }
        return $n;
    }

    private function upsertFormFieldI18n(Connection $db, array $rows): int
    {
        $n = 0;
        $sql = 'INSERT INTO {{%custom_form_field_i18n}} (
            [[field_id]], [[language]], [[label]], [[help_text]], [[options_json]]
        ) VALUES (
            :field_id, :language, :label, :help_text, :options_json
        ) ON DUPLICATE KEY UPDATE
            [[label]]=VALUES([[label]]),
            [[help_text]]=VALUES([[help_text]]),
            [[options_json]]=VALUES([[options_json]])';

        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['field_id']) || empty($row['language'])) {
                continue;
            }
            $exists = $db->createCommand('SELECT 1 FROM {{%custom_form_field}} WHERE [[id]]=:id', [':id' => $row['field_id']])->queryScalar();
            if (!$exists) {
                continue;
            }
            $db->createCommand($sql, [
                ':field_id' => (int)$row['field_id'],
                ':language' => (string)$row['language'],
                ':label' => $row['label'] ?? null,
                ':help_text' => $row['help_text'] ?? null,
                ':options_json' => $row['options_json'] ?? null,
            ])->execute();
            $n++;
        }
        return $n;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function mergeSettings(array $settings): int
    {
        $module = Yii::$app->getModule('thiscovery-translate', false);
        if (!$module) {
            return 0;
        }
        $n = 0;
        foreach ($settings as $name => $value) {
            $name = (string)$name;
            if ($name === '' || $this->isSecretSettingName($name)) {
                continue;
            }
            $module->settings->set($name, is_bool($value) ? ($value ? '1' : '0') : (string)$value);
            $n++;
        }
        return $n;
    }
}
