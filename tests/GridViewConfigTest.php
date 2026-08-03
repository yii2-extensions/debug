<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use yii\debug\GridViewConfig;
use yii\debug\tests\provider\GridViewConfigProvider;
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\DebugDataColumn;

/**
 * Unit tests for {@see GridViewConfig}, the static helper that drives consistent pager and table markup across every
 * GridView rendered inside the debug UI.
 *
 * {@see GridViewConfigProvider} for test case data providers.
 */
#[Group('grid-view-config')]
final class GridViewConfigTest extends TestCase
{
    public function testDefaultsContainerOptionsCarryYiiDebugGridClass(): void
    {
        $defaults = GridViewConfig::defaults();

        self::assertSame(
            ['class' => 'yii-debug-grid'],
            $defaults['options'],
            "options must declare the 'yii-debug-grid' wrapper class so summary/empty rows pick up scoped styling.",
        );
    }

    public function testDefaultsLayoutWrapsItemsBeforeSummaryAndPagerFooter(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-table-wrap">{items}</div>
            <div class="yii-debug-grid-footer">{summary}
            {pager}
            </div>
            HTML,
            GridViewConfig::defaults()['layout'],
            'Grid layout must keep the scrollable table before the summary and pager footer.',
        );
    }

    public function testDefaultsPagerOptionsEmitNamespacedPagerMarkup(): void
    {
        $pager = GridViewConfig::defaults()['pager'];

        self::assertSame(
            ['class' => 'yii-debug-pager'],
            $pager['options'],
            "Pager wrapper must use 'yii-debug-pager'.",
        );
        self::assertSame(
            ['class' => 'yii-debug-pager-item'],
            $pager['linkContainerOptions'],
            "Pager '<li>' elements must use 'yii-debug-pager-item'.",
        );
        self::assertSame(
            ['class' => 'yii-debug-pager-link'],
            $pager['linkOptions'],
            "Pager '<a>' elements must use 'yii-debug-pager-link'.",
        );
        self::assertSame(
            ['tag' => 'span', 'class' => 'yii-debug-pager-link'],
            $pager['disabledListItemSubTagOptions'],
            "Disabled pager items must render as '<span class=\"yii-debug-pager-link\"'>.",
        );
        self::assertSame(
            'is-active',
            $pager['activePageCssClass'],
            "Active pager item must use 'is-active' modifier.",
        );
        self::assertSame(
            'is-disabled',
            $pager['disabledPageCssClass'],
            "Disabled pager item must use 'is-disabled' modifier.",
        );
    }

    public function testDefaultsTableOptionsCarryYiiDebugTableClass(): void
    {
        $defaults = GridViewConfig::defaults();

        self::assertSame(
            ['class' => 'yii-debug-table'],
            $defaults['tableOptions'],
            "'tableOptions' must declare the 'yii-debug-table' class so the scoped Pico-style table styling applies.",
        );
    }

    public function testDefaultsUseAccessibleDataColumn(): void
    {
        self::assertSame(
            DebugDataColumn::class,
            GridViewConfig::defaults()['dataColumnClass'],
            'Every debug grid must use the data column that labels filters and scopes headers.',
        );
    }

    public function testPageSizeSelectorHtmlMarksCurrentPerPageOptionAsSelected(): void
    {
        $this->mockWebApplication();

        $_GET['per-page'] = '25';

        $html = GridViewConfig::pageSizeSelectorHtml();

        self::assertStringContainsString(
            '<option value="25" selected>',
            $html,
            "String 'per-page' value must round-trip as the selected option.",
        );
    }

    public function testPageSizeSelectorHtmlPreservesNumericPerPageThroughQueryParamString(): void
    {
        $this->mockWebApplication();

        $_GET['per-page'] = 100;

        $html = GridViewConfig::pageSizeSelectorHtml();

        self::assertStringContainsString(
            '<option value="100" selected>',
            $html,
            'Numeric query-param values must be coerced to the matching string option.',
        );
    }

    public function testPageSizeSelectorHtmlRendersCompleteControlContract(): void
    {
        $this->mockWebApplication();

        self::assertSame(
            <<<HTML
            <label class="yii-debug-grid-pagesize"><span class="yii-debug-grid-pagesize-label">Rows</span><select class="yii-debug-grid-pagesize-select" name="per-page" data-yii-debug-pagesize="true">
            <option value="10">
            10
            </option>
            <option value="25">
            25
            </option>
            <option value="50" selected>
            50
            </option>
            <option value="100">
            100
            </option>
            <option value="all">
            All
            </option>
            </select></label>
            HTML,
            GridViewConfig::pageSizeSelectorHtml(),
            'Page-size selector must preserve its data hook, option order, labels, and default selection.',
        );
    }

    public function testPaginationFromRequestCapsOversizedValuesAtOneThousand(): void
    {
        $this->mockWebApplication();

        $_GET['per-page'] = '2000';

        $pagination = GridViewConfig::paginationFromRequest();

        self::assertIsArray(
            $pagination,
            'Oversized per-page must still yield a pagination config.',
        );
        self::assertArrayHasKey(
            'pageSize',
            $pagination,
            "Pagination config must expose 'pageSize'.",
        );
        self::assertSame(
            1000,
            $pagination['pageSize'],
            'Oversized page requests must be capped at 1000 rows.',
        );
    }

    public function testPaginationFromRequestFallsBackToDefaultForNonPositiveValues(): void
    {
        $this->mockWebApplication();

        $_GET['per-page'] = '-5';

        $pagination = GridViewConfig::paginationFromRequest(75);

        self::assertIsArray(
            $pagination,
            'Negative per-page must still yield a pagination config.',
        );
        self::assertArrayHasKey(
            'pageSize',
            $pagination,
            "Pagination config must expose 'pageSize'.",
        );
        self::assertSame(
            75,
            $pagination['pageSize'],
            'Non-positive per-page must fall back to the supplied default.',
        );
    }

    public function testPaginationFromRequestFallsBackToDefaultForZero(): void
    {
        $this->mockWebApplication();

        $_GET['per-page'] = '0';

        $pagination = GridViewConfig::paginationFromRequest(75);

        self::assertIsArray(
            $pagination,
            'Zero per-page must still yield a pagination config.',
        );
        self::assertArrayHasKey(
            'pageSize',
            $pagination,
            "Pagination config must expose 'pageSize'.",
        );
        self::assertSame(
            75,
            $pagination['pageSize'],
            'Zero per-page must fall back to the supplied default.',
        );
    }

    public function testPaginationFromRequestReturnsFalseWhenPerPageEqualsAll(): void
    {
        $this->mockWebApplication();

        $_GET['per-page'] = 'all';

        self::assertFalse(
            GridViewConfig::paginationFromRequest(),
            "'per-page=all' must disable pagination entirely.",
        );
    }

    public function testPaginationFromRequestUsesCompleteDefaultContract(): void
    {
        $this->mockWebApplication();

        self::assertSame(
            [
                'pageSize' => 50,
                'pageSizeParam' => 'per-page',
                'pageSizeLimit' => false,
            ],
            GridViewConfig::paginationFromRequest(),
            'Missing per-page must retain the default size and unrestricted Yii page-size configuration.',
        );
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProviderExternal(GridViewConfigProvider::class, 'rowClassCases')]
    public function testRowClassForMapsLevelsToScopedRowClasses(string|null $level, array $expected): void
    {
        self::assertSame(
            $expected,
            GridViewConfig::rowClassFor($level),
            "'rowClassFor' must map known status levels to 'yii-debug-row-*' classes and ignore unknown ones.",
        );
    }
}
