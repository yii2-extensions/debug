<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\GridViewConfig;

/**
 * Unit tests for {@see GridViewConfig}, the static helper that drives consistent pager and table
 * markup across every GridView rendered inside the debug UI.
 *
 * {@see GridViewConfigTest::rowClassProvider} for status-to-class mapping data providers.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 2.1.29
 */
#[Group('grid-view-config')]
final class GridViewConfigTest extends TestCase
{
    /**
     * @return array<string, array{0: string|null, 1: array<string, mixed>}>
     */
    public static function rowClassProvider(): array
    {
        return [
            'success' => ['success', ['class' => 'yii-debug-row--success']],
            'info' => ['info', ['class' => 'yii-debug-row--info']],
            'warning' => ['warning', ['class' => 'yii-debug-row--warning']],
            'danger' => ['danger', ['class' => 'yii-debug-row--danger']],
            'error alias collapses to danger' => ['error', ['class' => 'yii-debug-row--danger']],
            'unknown level returns empty array' => ['exotic', []],
            'empty string returns empty array' => ['', []],
            'null returns empty array' => [null, []],
        ];
    }

    public function testDefaultsContainerOptionsCarryYiiDebugGridClass(): void
    {
        $defaults = GridViewConfig::defaults();

        self::assertSame(
            ['class' => 'yii-debug-grid'],
            $defaults['options'],
            'options must declare the `yii-debug-grid` wrapper class so summary/empty rows pick up scoped styling.',
        );
    }

    public function testDefaultsPagerOptionsEmitNamespacedPagerMarkup(): void
    {
        $pager = GridViewConfig::defaults()['pager'];

        self::assertSame(
            ['class' => 'yii-debug-pager'],
            $pager['options'],
            'Pager wrapper must use `yii-debug-pager`.',
        );
        self::assertSame(
            ['class' => 'yii-debug-pager__item'],
            $pager['linkContainerOptions'],
            'Pager <li> elements must use `yii-debug-pager__item`.',
        );
        self::assertSame(
            ['class' => 'yii-debug-pager__link'],
            $pager['linkOptions'],
            'Pager <a> elements must use `yii-debug-pager__link`.',
        );
        self::assertSame(
            ['tag' => 'span', 'class' => 'yii-debug-pager__link'],
            $pager['disabledListItemSubTagOptions'],
            'Disabled pager items must render as <span class="yii-debug-pager__link">.',
        );
        self::assertSame('is-active', $pager['activePageCssClass'], 'Active pager item must use `is-active` modifier.');
        self::assertSame('is-disabled', $pager['disabledPageCssClass'], 'Disabled pager item must use `is-disabled` modifier.');
    }
    public function testDefaultsTableOptionsCarryYiiDebugTableClass(): void
    {
        $defaults = GridViewConfig::defaults();

        self::assertSame(
            ['class' => 'yii-debug-table'],
            $defaults['tableOptions'],
            'tableOptions must declare the `yii-debug-table` class so the scoped Pico-style table styling applies.',
        );
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('rowClassProvider')]
    public function testRowClassForMapsLevelsToScopedRowClasses(string|null $level, array $expected): void
    {
        self::assertSame(
            $expected,
            GridViewConfig::rowClassFor($level),
            'rowClassFor must map known status levels to `yii-debug-row--*` classes and ignore unknown ones.',
        );
    }
}
