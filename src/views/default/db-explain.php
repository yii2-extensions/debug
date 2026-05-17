<?php

declare(strict_types=1);

use UIAwesome\Html\Helper\Encode;

/**
 * @var \yii\web\View $this
 * @var array<int, array<string, scalar|null>> $results
 * @var string $query
 */

$this->title = 'EXPLAIN';

$resultList = array_values($results);
$columns = $resultList === [] ? [] : array_keys($resultList[0]);
?>
<div class="yii-debug-explain">
    <h1 class="yii-debug-explain-title">EXPLAIN</h1>

    <?php if ($query !== ''): ?>
        <pre class="yii-debug-explain-query"><?= Encode::content($query) ?></pre>
    <?php endif; ?>

    <?php if ($results === []): ?>
        <p class="yii-debug-explain-empty">EXPLAIN returned no rows.</p>
    <?php else: ?>
        <div class="yii-debug-explain-scroll">
            <table class="yii-debug-table yii-debug-explain-table">
                <thead>
                    <tr>
                        <?php foreach ($columns as $column): ?>
                            <th><?= Encode::content($column) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <?php foreach ($columns as $column): ?>
                                <?php $value = $row[$column] ?? null; ?>
                                <td><?= $value === null || $value === '' ? '<em>NULL</em>' : Encode::content((string) $value) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
