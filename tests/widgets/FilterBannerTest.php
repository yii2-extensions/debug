<?php

declare(strict_types=1);

namespace yii\debug\tests\widgets;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\{LogTarget, Module};
use yii\debug\models\search\LogSearch;
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\FilterBanner;
use yii\web\Controller;

/**
 * Unit tests for {@see FilterBanner} covering the no-filters short-circuit, the pill list rendering, the per-attribute
 * removal URL composition, the `Clear all` URL composition, the singular/plural label, and the missing-search-model
 * configuration error.
 */
#[Group('widget')]
#[Group('filter-banner')]
final class FilterBannerTest extends TestCase
{
    public function testRunPreservesOtherQueryParamsInRemovalLinks(): void
    {
        $this->bootApp();

        $_GET['LogSearch'] = ['category' => 'app'];
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

    public function testRunRendersPluralLabelForMultipleActiveFilters(): void
    {
        $this->bootApp();

        $_GET['LogSearch'] = ['category' => 'app', 'message' => 'login'];

        $html = FilterBanner::widget(['searchModel' => new LogSearch()]);

        self::assertStringContainsString(
            '2 filters active',
            $html,
            'Multiple active filters must use the plural label form.',
        );
    }

    public function testRunRendersSingularLabelForSingleActiveFilter(): void
    {
        $this->bootApp();

        $_GET['LogSearch'] = ['category' => 'app'];

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

        $_GET['LogSearch'] = ['category' => 'app', 'message' => '', 'level' => null, 'bag' => ['nested']];

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
            'searchModel must be set',
        );

        FilterBanner::widget();
    }

    private function bootApp(): void
    {
        $this->mockWebApplication();

        Yii::$app->getRequest()->setUrl('dummy');

        $module = new Module('debug');
        $module->logTarget = new LogTarget($module);

        Yii::$app->controller = new Controller('debug', $module);
    }
}
