<?php

/**
 * Inline ? guidance (same pattern as Thiscovery Navigation).
 *
 * @var string|null $text
 * @var string|null $html
 */

use yii\helpers\Html;

$text = trim((string)($text ?? ''));
$html = $html ?? null;
if ($html === null && $text === '') {
    return;
}
$id = 'tt-guide-' . str_replace('.', '', uniqid('', true));
$label = Yii::t('ThiscoveryTranslateModule.base', 'Guidance');
?>
<button type="button" class="tt-guide__toggle" data-tt-guide-toggle
        aria-expanded="false" aria-controls="<?= Html::encode($id) ?>"
        title="<?= Html::encode($label) ?>" aria-label="<?= Html::encode($label) ?>">
    <i class="fa fa-question-circle" aria-hidden="true"></i>
</button>
<div id="<?= Html::encode($id) ?>" class="tt-guide__panel" hidden>
    <?php if ($html !== null): ?>
        <?= $html ?>
    <?php else: ?>
        <p><?= Html::encode($text) ?></p>
    <?php endif; ?>
</div>
