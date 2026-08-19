<?php

declare(strict_types=1);

use PHPForge\Debug\Panel\Db\DbExplainRenderer;
use yii\web\View;

/**
 * @var string $query Explain query string.
 * @var array<int, array<string, scalar|null>> $results Explain query results.
 * @var View $this View component instance.
 */
$this->title = 'EXPLAIN';
?>
<?= DbExplainRenderer::render($query, $results);
