<?php

declare(strict_types=1);

namespace yii\debug\tests\widgets;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\actions\IndexAction;
use yii\debug\exception\Message;
use yii\debug\{LogTarget, Module};
use yii\debug\models\search\{LogSearch, ProfileSearch};
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\FilterBanner;
use yii\helpers\{Html, Url};

/**
 * Unit tests for {@see FilterBanner} covering the no-filters short-circuit, the pill list rendering, the per-attribute
 * removal URL composition, the `Clear all` URL composition, the singular/plural label, and the missing-search-model
 * configuration error.
 */
#[Group('widget')]
#[Group('filter-banner')]
final class FilterBannerTest extends TestCase
{
    public function testNormalizedFiltersHideInvalidValuesAndClearTheirRawKeys(): void
    {
        $this->bootApp();

        $_GET['Profile'] = ['duration' => 'invalid', 'category' => 'db'];

        $searchModel = new ProfileSearch();

        $searchModel->search($_GET, []);

        $html = FilterBanner::widget(
            [
                'activeFilters' => $searchModel->getAttributes(),
                'searchModel' => $searchModel,
            ],
        );

        self::assertStringContainsString(
            '1 filter active',
            $html,
            'Only the normalized category filter must count as active.',
        );
        self::assertStringContainsString(
            '>category<',
            $html,
            'The valid normalized category must remain visible.',
        );
        self::assertStringNotContainsString(
            '>duration<',
            $html,
            'An ignored invalid duration must not render as an active-filter pill.',
        );
        self::assertStringNotContainsString(
            '>invalid<',
            $html,
            'An ignored invalid value must not be presented as active.',
        );

        $expectedClearUrl = Url::to(['/debug/index']);

        self::assertStringContainsString(
            'class="yii-debug-active-filters-clear" href="' . Html::encode($expectedClearUrl) . '"',
            $html,
            'Clear all must remove both the visible category and the raw invalid duration key.',
        );
    }

    public function testRunPreservesOtherQueryParamsInRemovalLinks(): void
    {
        $this->bootApp();

        $_GET['Log'] = ['category' => 'app'];
        $_GET['sort'] = 'time';
        $_GET['page'] = 3;

        $html = FilterBanner::widget(['searchModel' => new LogSearch()]);

        self::assertStringContainsString(
            'sort=time',
            $html,
            'Removal links must preserve unrelated query params (sort, theme, ...).',
        );
        self::assertStringNotContainsString(
            'page=',
            $html,
            "Removal links must drop the 'page' cursor so the user lands on page one.",
        );
    }

    public function testRunProvidesAccessibleNamesForRemovalLinks(): void
    {
        $this->bootApp();

        $_GET['Log'] = ['category' => 'app', 'message' => 'login'];

        $html = FilterBanner::widget(['searchModel' => new LogSearch()]);

        self::assertStringContainsString(
            'aria-label="Remove category: app filter"',
            $html,
            'Each filter pill must name the filter its link removes.',
        );
        self::assertStringContainsString(
            'aria-label="Remove message: login filter"',
            $html,
            'Every active filter must expose a distinct removal-link name.',
        );
        self::assertStringContainsString(
            'aria-label="Clear all active filters"',
            $html,
            "The 'Clear all' link must expose its complete action to assistive technology.",
        );
    }

    public function testRunRendersPluralLabelForMultipleActiveFilters(): void
    {
        $this->bootApp();

        $_GET['Log'] = ['category' => 'app', 'message' => 'login'];

        $html = FilterBanner::widget(['searchModel' => new LogSearch()]);

        self::assertStringContainsString(
            '2 filters active',
            $html,
            'Multiple active filters must use the plural label form.',
        );
        self::assertSame(
            2,
            substr_count($html, 'class="yii-debug-active-filter-pill"'),
            'Every active filter must render its own removal pill.',
        );

        $expectedCategoryRemovalUrl = Url::to(
            [
                '/debug/index',
                'Log' => ['message' => 'login'],
            ],
        );

        self::assertStringContainsString(
            'href="' . Html::encode($expectedCategoryRemovalUrl) . '"',
            $html,
            'Removal must target the dispatched action route and keep the other filter.',
        );
    }

    public function testRunRendersSingularLabelForSingleActiveFilter(): void
    {
        $this->bootApp();

        $_GET['Log'] = ['category' => 'app'];

        $html = FilterBanner::widget(['searchModel' => new LogSearch()]);

        self::assertStringContainsString(
            '1 filter active',
            $html,
            'Single active filter must use the singular label form.',
        );
        self::assertStringContainsString(
            '>Clear all<',
            $html,
            "The 'Clear all' action must be present on every rendered banner.",
        );
    }

    public function testRunReturnsEmptyMarkupWhenNoFiltersAreActive(): void
    {
        $this->bootApp();

        self::assertSame(
            '',
            FilterBanner::widget(['searchModel' => new LogSearch()]),
            'No active filters must collapse the banner to an empty string.',
        );
    }

    public function testRunSkipsEmptyAndNonScalarFilterValues(): void
    {
        $this->bootApp();

        $_GET['Log'] = ['message' => '', 'bag' => ['nested'], 'category' => 'app', 'level' => null];

        $html = FilterBanner::widget(['searchModel' => new LogSearch()]);

        self::assertStringContainsString(
            '>category<',
            $html,
            'Scalar non-empty filters must surface as a pill.',
        );
        self::assertStringNotContainsString(
            '>message<',
            $html,
            'Empty filter values must be skipped.',
        );
        self::assertStringNotContainsString(
            '>level<',
            $html,
            'Null filter values must be skipped.',
        );
        self::assertStringNotContainsString(
            '>bag<',
            $html,
            'Non-scalar filter values must be skipped.',
        );
    }

    public function testThrowInvalidConfigExceptionWhenSearchModelIsMissing(): void
    {
        $this->bootApp();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::SEARCH_MODEL_REQUIRED->getMessage(FilterBanner::class),
        );

        FilterBanner::widget();
    }

    private function bootApp(): void
    {
        $this->mockWebApplication();

        Yii::$app->getRequest()->setUrl('dummy');

        $module = new Module('debug');
        $module->logTarget = new LogTarget($module);

        // Debugger pages dispatch as standalone module actions (no controller), so filter links must derive their
        // route from the requested action.
        $action = new IndexAction('index');

        $action->setModule($module);

        Yii::$app->requestedAction = $action;
    }
}
