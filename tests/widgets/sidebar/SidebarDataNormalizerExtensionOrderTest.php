<?php

declare(strict_types=1);

namespace yii\debug\tests\widgets\sidebar;

use PHPForge\Debug\View\Sidebar\SidebarNavItem;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\Module;
use yii\debug\tests\support\stub\cache\{AlphaPanel, BetaPanel, CachePanel};
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\sidebar\SidebarDataNormalizer;

use function array_flip;
use function array_intersect_key;
use function array_map;

/**
 * Unit tests for {@see SidebarDataNormalizer} pinning the order configured `position` values give to the Extensions
 * group.
 */
#[Group('panel')]
#[Group('sidebar')]
final class SidebarDataNormalizerExtensionOrderTest extends TestCase
{
    public function testFromIndexGroupsEveryConfiguredExtensionUnderOneLabel(): void
    {
        $this->mockWebApplication();

        $module = new Module(
            'debug',
            null,
            [
                'panels' => [
                    'beta' => ['class' => BetaPanel::class, 'position' => 1],
                    'alpha' => ['class' => AlphaPanel::class, 'position' => 2],
                    'cache' => CachePanel::class,
                ],
            ],
        );

        $panels = array_intersect_key($module->panels, array_flip(['alpha', 'beta', 'cache']));

        $view = SidebarDataNormalizer::fromIndex($panels, ['tag-newest' => $this->requestSummary('tag-newest')]);

        self::assertArrayHasKey(
            'Extensions',
            $view->navGroups,
            'Portable entries need a labeled group.',
        );
        self::assertCount(
            3,
            $view->navGroups['Extensions'],
            'Every configured portable entry must reach the group.',
        );
    }

    public function testFromIndexListsPositionedExtensionsBeforeTheRemainingOnes(): void
    {
        $this->mockWebApplication();

        $module = new Module(
            'debug',
            null,
            [
                'panels' => [
                    'beta' => ['class' => BetaPanel::class, 'position' => 1],
                    'alpha' => ['class' => AlphaPanel::class, 'position' => 2],
                    'cache' => CachePanel::class,
                ],
            ],
        );

        $panels = array_intersect_key($module->panels, array_flip(['alpha', 'beta', 'cache']));

        $view = SidebarDataNormalizer::fromIndex($panels, ['tag-newest' => $this->requestSummary('tag-newest')]);

        self::assertSame(
            ['Beta', 'Alpha', 'Cache'],
            array_map(
                static fn(SidebarNavItem $item): string => $item->label,
                $view->navGroups['Extensions'] ?? [],
            ),
            'Order: positions ascending, then the rest by title.',
        );
    }
}
