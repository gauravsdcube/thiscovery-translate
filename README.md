# Thiscovery Translate

HumHub module for **semantic** Amazon Translate (data layer, not DOM rewriting): persistent translations, translation memory, Forms pre-translation, and cost controls.

## Features

- Object-field translation store with translation memory and usage tracking
- Amazon Translate via EC2 IAM / SigV4 (`eu-west-2` by default)
- Forms: queue-based pre-translate; fill views use DB overlays only
- Site language picker and optional UI missing-string assist
- Admin settings for site vs forms features and budgets

## Requirements

- HumHub ≥ 1.18
- AWS IAM permission `translate:TranslateText` (plus terminology APIs when enabled)

## Ops

```bash
php protected/yii migrate/up --migrationPath=@thiscovery-translate/migrations --interactive=0
php protected/yii thiscovery-translate/smoke
php protected/yii thiscovery-translate/accept
```

Enable under **Admin → Modules → Thiscovery Translate**. See `docs/ARCHITECTURE.md` and `docs/CHANGELOG.md`.


## Data migration (instances)

Export translation rows, translation memory, terminology, and non-secret module settings (never usage / secrets):

```bash
php protected/yii thiscovery-translate/export/data
php protected/yii thiscovery-translate/export/data /tmp/tt-export.json --forms=1
php protected/yii thiscovery-translate/import/data /tmp/tt-export.json
php protected/yii thiscovery-translate/import/data /tmp/tt-export.json --settings=1
```

Default export path: `@runtime/thiscovery-translate-export-YYYYMMDD-HHMMSS.json`.

**Caveat:** object-field rows key on `object_id` — IDs must match on the target instance. Translation memory reuses by `source_hash` and is safe to share across instances even when object IDs differ.

## License

AGPL-3.0-or-later. Copyright (c) 2026 D Cube Consulting.
