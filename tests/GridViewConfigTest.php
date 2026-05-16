<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use yii\debug\GridViewConfig;
use yii\debug\tests\provider\GridViewConfigProvider;
use yii\debug\tests\support\TestCase;

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

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProviderExternal(GridViewConfigProvider::class, 'rowClassCases')]
    public function testRowClassForMapsLevelsToScopedRowClasses(string|null $level, array $expected): void
    {
        self::assertSame(
            $expected,
            GridViewConfig::rowClassFor($level),
            "'rowClassFor' must map known status levels to 'yii-debug-row--*' classes and ignore unknown ones.",
        );
    }
}
