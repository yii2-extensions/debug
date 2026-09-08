<?php

declare(strict_types=1);

namespace yii\debug\tests\widgets;

use PHPForge\Debug\View\Grid\GridCount;
use PHPUnit\Framework\Attributes\Group;
use yii\data\ArrayDataProvider;
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\GridView;

/**
 * Unit tests for {@see GridView} covering the shared row-count sentence it renders in place of the framework summary.
 */
#[Group('widget')]
#[Group('grid-view')]
final class GridViewTest extends TestCase
{
    public function testRenderSummaryCountsTheWholeSetWhenPaginationIsDisabled(): void
    {
        $this->mockWebApplication();

        $grid = $this->makeGrid(['a', 'b'], false);

        self::assertSame(
            GridCount::render(1, 2, 2),
            $grid->renderSummary(),
            'Disabled pagination must report the whole set as one page.',
        );
    }

    public function testRenderSummaryOmitsBoldMarkupOfTheFrameworkSummary(): void
    {
        $this->mockWebApplication();

        $summary = $this
            ->makeGrid(['a', 'b'])
            ->renderSummary();

        self::assertStringNotContainsString(
            '<b>',
            $summary,
            'The framework emphasis markup must be gone.',
        );
        self::assertStringContainsString(
            'class="summary yii-debug-grid-count"',
            $summary,
            'The shared wrapper class must be kept.',
        );
    }

    public function testRenderSummaryOpensTheRangeAtThePaginatorOffset(): void
    {
        $this->mockWebApplication();

        $_GET['page'] = '2';

        $grid = $this->makeGrid(['a', 'b'], ['pageSize' => 1]);

        self::assertSame(
            GridCount::render(2, 2, 2),
            $grid->renderSummary(),
            'The offset must open the range on later pages.',
        );
    }

    public function testRenderSummaryReportsSingleItemForOneRow(): void
    {
        $this->mockWebApplication();

        $grid = $this->makeGrid(['a']);

        self::assertSame(
            GridCount::render(1, 1, 1),
            $grid->renderSummary(),
            'A single row must close the range on itself.',
        );
        self::assertStringContainsString(
            'of 1 item.',
            $grid->renderSummary(),
            'A single row must use the singular noun.',
        );
    }

    public function testRenderSummaryReportsTheFullRangeForTwoRows(): void
    {
        $this->mockWebApplication();

        $grid = $this->makeGrid(['a', 'b']);

        self::assertSame(
            GridCount::render(1, 2, 2),
            $grid->renderSummary(),
            'Two rows must span the whole range.',
        );
    }

    public function testRenderSummaryReportsZeroRangeForAnEmptyPage(): void
    {
        $this->mockWebApplication();

        $grid = $this->makeGrid([]);

        self::assertSame(
            GridCount::render(0, 0, 0),
            $grid->renderSummary(),
            'An empty page must report a zero range instead of collapsing.',
        );
        self::assertStringContainsString(
            'Showing 0-0 of 0 items.',
            $grid->renderSummary(),
            'The sentence must match the Yii3 footer.',
        );
    }

    /**
     * @param list<string> $models Rows backing the grid.
     * @param array<string, mixed>|false $pagination Pagination config, or `false` to disable pagination.
     */
    private function makeGrid(array $models, array|false $pagination = []): GridView
    {
        return new GridView(
            [
                'dataProvider' => new ArrayDataProvider(
                    [
                        'allModels' => $models,
                        'pagination' => $pagination,
                    ],
                ),
            ],
        );
    }
}
