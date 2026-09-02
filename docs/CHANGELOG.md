# Changelog

All notable changes to this module are documented in this file.

## 2.1.0 (September 2, 2026)

- Enh: Console export/import for translation store, memory, and terminology (`thiscovery-translate/export/data`, `thiscovery-translate/import/data`); optional `--forms=1` for form i18n overlays and `--settings=1` on import for safe module settings
- Enh: SpaceHook translates Space name, description, about, and tags for display without mutating stored rows (`decorateForDisplay`)
- Enh: SpaceExperienceHook translates TSE welcome title/content/actions, highlights, and resource titles/descriptions
- Enh: PageBuilderHook page chrome translation (title, summary, category, top menu label) for engagement pages
- Enh: RichText before-output hook covers Space about/description (including about page without a record)
- Enh: Widget events decorate Space directory cards, space chooser items, space header, and container profile header for translated display

## 2.0.0 (September 2, 2026)

- Enh: Semantic Amazon Translate pipeline with persistent object-field store, translation memory, and cost tracking (eu-west-2 IAM)
- Enh: Forms pre-translate via queue into form/field i18n tables; participant fill uses DB overlay only (no live AWS)
- Enh: Site UI vs Forms feature split with Admin settings, UI missing-string assist, and language picker widget
- Enh: Resolution hierarchy: manual/locked override → HumHub Yii::t → store → TM → Amazon Translate → source fallback
- Enh: Optional free-text response translation for evaluators without overwriting originals
- Enh: Smoke and accept console commands for ops verification

