<?php

declare(strict_types=1);

use yii\debug\panels\DbPanel;

/**
 * @var DbPanel $panel
 * @var int $queryCount
 * @var int $queryTime
 * @var int $excessiveCallerCount
 */

$title = "Executed $queryCount database queries which took $queryTime.";
$warning = '';

if ($panel->isQueryCountCritical($queryCount)) {
    $warning .= "Too many queries, allowed count is {$panel->criticalQueryThreshold}.";
}

if ($excessiveCallerCount > 0) {
    $warning .= ($warning !== ''
        ? ' &#10;'
        : '') . $excessiveCallerCount . ' ' . ($excessiveCallerCount === 1 ? 'caller is' : 'callers are')
        . ' making too many calls.';
}

?>
<?php if ($queryCount > 0): ?>
    <div class="yii-debug-toolbar-block">
        <a href="<?= $panel->getUrl() ?>" title="<?= $title ?>">
            <?= $panel->getSummaryName() ?>
            <span class="yii-debug-toolbar-label yii-debug-toolbar-label-info"><?= $queryCount ?></span>
            <?php if ($warning !== ''): ?>
                <span title="<?= $warning ?>">&#x26a0;</span>
            <?php endif; ?>
            <span class="yii-debug-toolbar-label"><?= $queryTime ?></span>
        </a>
    </div>
<?php endif;
