# Thiscovery Translate 2.0 — Architecture rebuild

Semantic data-layer translation (not DOM rewriting).

## Resolution hierarchy

1. Manual / locked override  
2. HumHub native Yii::t catalog (UI assist only when missing)  
3. Object field store (`thiscovery_translation`)  
4. Translation memory  
5. Amazon Translate (once) → persist + TM + usage  
6. Source text on failure / budget

## Policies

- Platform / UGC: **lazy** on first view  
- Forms: **pre-translate** via queue into `custom_form_i18n` / `custom_form_field_i18n`  
- Participant fill: **DB overlay only** (no AWS)  
- Free-text responses: optional evaluator translate; originals never overwritten  

## AWS

- Region default `eu-west-2`  
- EC2 IAM instance role + SigV4 (`AwsTranslateClient`)  
- Required: `translate:TranslateText` (+ terminology APIs when enabled)

## Ops

```bash
php protected/yii migrate/up --migrationPath=@thiscovery-translate/migrations --interactive=0
php protected/yii thiscovery-translate/smoke
php protected/yii thiscovery-translate/accept
```

Enable under Admin → Thiscovery Translate after verifying smoke.
