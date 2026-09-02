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

## License

AGPL-3.0-or-later. Copyright (c) 2026 D Cube Consulting.
