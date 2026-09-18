<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\{Module, Panel, ToolbarDataMapper};
use yii\debug\tests\support\stub\cache\{AlphaPanel, BetaPanel, CachePanel};
use yii\debug\tests\support\TestCase;

use function array_flip;
use function array_intersect_key;

/**
 * Unit tests for {@see ToolbarDataMapper} pinning the order configured `position` values give to extension chips.
 */
#[Group('toolbar')]
final class ToolbarDataMapperExtensionOrderTest extends TestCase
{
    public function testMapListsPositionedExtensionChipsBeforeTheRemainingOnes(): void
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

        foreach ($panels as $panel) {
            $panel->tag = 'capture-tag';

            $panel->hydrate(['operations' => []]);
        }

        $result = ToolbarDataMapper::create('capture-tag', 'Yii Debugger')
            ->withNavigation('/debug/index')
            ->map($panels);

        $titles = [];

        foreach ($result['items'] as $item) {
            if (($item['extension'] ?? null) === true) {
                $titles[] = $item['title'] ?? null;
            }
        }

        self::assertSame(
            ['Beta', 'Alpha', 'Cache'],
            $titles,
            'Order: positions ascending, then the rest by title.',
        );
    }

    public function testMapRegistersEveryConfiguredExtensionChip(): void
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

        $registered = array_intersect_key($module->panels, array_flip(['alpha', 'beta', 'cache']));

        self::assertCount(
            3,
            $registered,
            'Every configured portable entry must survive registration.',
        );
        self::assertContainsOnlyInstancesOf(
            Panel::class,
            $registered,
            'Portable entries must be adapted to the host panel type.',
        );
    }
}
