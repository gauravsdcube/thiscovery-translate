# Changelog

All notable changes to this module are documented in this file.

## 2.0.0 (September 2, 2026)

- Enh: Semantic Amazon Translate pipeline with persistent object-field store, translation memory, and cost tracking (eu-west-2 IAM)
- Enh: Forms pre-translate via queue into form/field i18n tables; participant fill uses DB overlay only (no live AWS)
- Enh: Site UI vs Forms feature split with Admin settings, UI missing-string assist, and language picker widget
- Enh: Resolution hierarchy: manual/locked override → HumHub Yii::t → store → TM → Amazon Translate → source fallback
- Enh: Optional free-text response translation for evaluators without overwriting originals
- Enh: Smoke and accept console commands for ops verification

