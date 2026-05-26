<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\{Div, P, Pre};
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Phrasing\Em;
use UIAwesome\Html\Table\{Table, Tbody, Td, Th, Thead, Tr};
use yii\web\View;

/**
 * @var string $query Explain query string.
 * @var array<int, array<string, scalar|null>> $results Explain query results.
 * @var View $this View component instance.
 */
$this->title = 'EXPLAIN';

$resultList = array_values($results);
$columns = $resultList === [] ? [] : array_keys($resultList[0]);

$children = [
    H1::tag()
        ->class('yii-debug-explain-title')
        ->content('EXPLAIN'),
];

if ($query !== '') {
    $children[] = Pre::tag()
        ->class('yii-debug-explain-query')
        ->content($query);
}

if ($results === []) {
    $children[] = P::tag()
        ->class('yii-debug-explain-empty')
        ->content('EXPLAIN returned no rows.');
} else {
    $headerCells = [];

    foreach ($columns as $column) {
        $headerCells[] = Th::tag()->content($column);
    }

    $bodyRows = [];

    foreach ($results as $row) {
        $cells = [];

        foreach ($columns as $column) {
            $value = $row[$column] ?? null;
            $cells[] = $value === null || $value === ''
                ? Td::tag()->html(Em::tag()->content('NULL'))
                : Td::tag()->content((string) $value);
        }

        $bodyRows[] = Tr::tag()->html(...$cells);
    }

    $children[] = Div::tag()
        ->class('yii-debug-explain-scroll')
        ->html(
            Table::tag()
                ->class('yii-debug-table yii-debug-explain-table')
                ->html(
                    Thead::tag()->html(Tr::tag()->html(...$headerCells)),
                    Tbody::tag()->html(...$bodyRows),
                ),
        );
}
?>
<?= Div::tag()
    ->class('yii-debug-explain')
    ->html(...$children);
