<?php

declare(strict_types=1);

use PHPForge\Debug\Panel\Db\DbExplainRenderer;
use yii\web\View;

/**
 * @var string|null $error Escaped by the shared renderer when the database rejects the EXPLAIN statement.
 * @var string $query Explain query string.
 * @var array<int, array<string, scalar|null>> $results Explain query results.
 * @var View $this View component instance.
 */
$this->title = 'EXPLAIN';
?>
<?= $error === null
    ? DbExplainRenderer::render($query, $results)
    : DbExplainRenderer::renderError($query, $error);
