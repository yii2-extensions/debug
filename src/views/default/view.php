<?php

declare(strict_types=1);

use yii\debug\Panel;
use yii\web\View;

/**
 * @var Panel $activePanel Active panel for the current request view.
 * @var array<int|string, mixed> $manifest Reverse-ordered (newest first) tag-to-summary map.
 * @var Panel[] $panels Debug panels keyed by id.
 * @var array<string, mixed> $summary Active request summary (method, URL, status, time).
 * @var string $tag Active request tag.
 * @var View $this View component instance.
 */
$this->title = 'Yii Debugger';

?>
<?= $activePanel->getDetail();
