<?php

declare(strict_types=1);

namespace yii\debug\tests\timeline;

use PHPForge\Debug\Panel\Profile\ProfileRow;
use PHPForge\Debug\Panel\Timeline\TimelineSnapshot;
use PHPForge\Debug\Panel\Timeline\TimelineSpanRow;
use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\{LogTarget, Module};
use yii\debug\models\timeline\DataProvider;
use yii\debug\panels\TimelinePanel;
use yii\debug\tests\support\TestCase;
use yii\web\Controller;

/**
 * Unit tests for {@see DataProvider} covering the memory tuple, the adaptive ruler ticks, and the child-overlap
 * tracking.
 */
#[Group('timeline')]
final class DataProviderTest extends TestCase
{
    public function testGeometryUsesRequestStartAndDuration(): void
    {
        $panel = $this->stubPanel();

        $this->setInaccessibleProperty($panel, 'start', 1_000.0);
        $this->setInaccessibleProperty($panel, 'duration', 200.0);

        $provider = new DataProvider($panel);
        $row = new ProfileRow(1_025.0, 10.0, 'span', '', 0, 0, 0, 0, []);

        self::assertSame(
            25.0,
            $provider->getTime($row),
            'Elapsed time must be relative to the request start.',
        );
        self::assertSame(
            12.5,
            $provider->getLeft($row),
            'Left offset must be the elapsed share of the request duration.',
        );
        self::assertSame(
            5.0,
            $provider->getWidth($row),
            'Width must be the span share of the request duration.',
        );
    }

    public function testGetRulersDropsTickCrowdingTheRightEdge(): void
    {
        self::assertSame(
            [0 => 0.0, 2000 => 2000 / 6050 * 100, 4000 => 4000 / 6050 * 100],
            $this->rulersFor(6050.0),
            'A tick within a quarter step of the edge must be dropped.',
        );
        self::assertSame(
            [0 => 0.0, 2000 => 2000 / 6450 * 100, 4000 => 4000 / 6450 * 100],
            $this->rulersFor(6450.0),
            'A tick just inside the quarter-step margin must still be dropped.',
        );
    }

    public function testGetRulersKeepsEdgeTickAtExactQuarterStepLimit(): void
    {
        self::assertSame(
            [0 => 0.0, 2000 => 2000 / 6500 * 100, 4000 => 4000 / 6500 * 100, 6000 => 6000 / 6500 * 100],
            $this->rulersFor(6500.0),
            'A tick landing exactly on the quarter-step limit must be kept.',
        );
        self::assertSame(
            [0 => 0.0, 1 => 80.0],
            $this->rulersFor(1.25),
            'A unit tick equal to the complete ruler limit must be kept.',
        );
    }

    public function testGetRulersKeepsFactorBoundariesOnTheSmallerStep(): void
    {
        self::assertSame(
            [
                0 => 0.0,
                100 => 100 / 600 * 100,
                200 => 200 / 600 * 100,
                300 => 300 / 600 * 100,
                400 => 400 / 600 * 100,
                500 => 500 / 600 * 100,
            ],
            $this->rulersFor(600.0),
            'A 600 ms capture must tick every 100 ms.',
        );
        self::assertSame(
            [
                0 => 0.0,
                200 => 200 / 1200 * 100,
                400 => 400 / 1200 * 100,
                600 => 600 / 1200 * 100,
                800 => 800 / 1200 * 100,
                1000 => 1000 / 1200 * 100,
            ],
            $this->rulersFor(1200.0),
            'A 1.2 s capture must tick every 200 ms.',
        );
        self::assertSame(
            [
                0 => 0.0,
                500 => 500 / 3000 * 100,
                1000 => 1000 / 3000 * 100,
                1500 => 1500 / 3000 * 100,
                2000 => 2000 / 3000 * 100,
                2500 => 2500 / 3000 * 100,
            ],
            $this->rulersFor(3000.0),
            'A 3 s capture must tick every 500 ms.',
        );
    }

    public function testGetRulersPicksNiceStepsAcrossMagnitudes(): void
    {
        self::assertSame(
            [0 => 0.0, 2 => 25.0, 4 => 50.0, 6 => 75.0],
            $this->rulersFor(8.0),
            'An 8 ms capture must tick every 2 ms.',
        );
        self::assertSame(
            [0 => 0.0, 10 => 10 / 47 * 100, 20 => 20 / 47 * 100, 30 => 30 / 47 * 100, 40 => 40 / 47 * 100],
            $this->rulersFor(47.0),
            'A 47 ms capture must tick every 10 ms.',
        );
        self::assertSame(
            [0 => 0.0, 50 => 50 / 230 * 100, 100 => 100 / 230 * 100, 150 => 150 / 230 * 100, 200 => 200 / 230 * 100],
            $this->rulersFor(230.0),
            'A 230 ms capture must tick every 50 ms.',
        );
        self::assertSame(
            [0 => 0.0, 10000 => 10000 / 36000 * 100, 20000 => 20000 / 36000 * 100, 30000 => 30000 / 36000 * 100],
            $this->rulersFor(36000.0),
            'A 36 s capture must tick every 10 s.',
        );
    }

    public function testGetRulersReturnsEmptyArrayForNonPositiveDuration(): void
    {
        self::assertSame(
            [],
            $this->rulersFor(0.0),
            'Zero duration must disable the ruler.',
        );
        self::assertSame(
            [],
            $this->rulersFor(-5.0),
            'Negative duration must disable the ruler.',
        );
    }

    public function testGetRulersReturnsEmptyArrayForZeroLines(): void
    {
        $panel = $this->stubPanel();

        $provider = new DataProvider($panel);

        self::assertSame(
            [],
            $provider->getRulers(0),
            'Zero ruler lines must disable the ruler entirely.',
        );
    }

    public function testGetRulersReturnsOriginOnlyWhenStepOutgrowsDuration(): void
    {
        self::assertSame(
            [0 => 0.0],
            $this->rulersFor(1200.0, 1),
            'A single-tick target must keep only the origin.',
        );
    }

    public function testGetRulersRoundsIntermediateFactorsUpToTheNextNiceStep(): void
    {
        self::assertSame(
            [0 => 0.0, 500 => 500 / 1500 * 100, 1000 => 1000 / 1500 * 100],
            $this->rulersFor(1500.0),
            'A 1.5 s capture must round up to 500 ms steps.',
        );
        self::assertSame(
            [0 => 0.0, 1000 => 1000 / 3300 * 100, 2000 => 2000 / 3300 * 100, 3000 => 3000 / 3300 * 100],
            $this->rulersFor(3300.0),
            'A 3.3 s capture must round up to 1 s steps.',
        );
    }

    public function testGetRulersUsesUnitStepForTinyDurations(): void
    {
        self::assertSame(
            [0 => 0.0, 1 => 1 / 3 * 100, 2 => 2 / 3 * 100],
            $this->rulersFor(3.0),
            'A 3 ms capture must tick every millisecond.',
        );
    }

    public function testPrepareModelsIndentsSpansByProfilerNestingLevel(): void
    {
        $panel = $this->stubPanel();

        $provider = new DataProvider(
            $panel,
            [
                'allModels' => [
                    new ProfileRow(0.0, 50.0, 'outer', '', 0, 0, 0, 0, []),
                    new ProfileRow(1.0, 20.0, 'inner', '', 1, 1, 0, 0, []),
                ],
            ],
        );

        $models = $provider->getModels();

        self::assertCount(
            2,
            $models,
            'Both rows must be prepared.',
        );

        $outer = $models[0] ?? self::fail('Expected the outer span row.');
        $inner = $models[1] ?? self::fail('Expected the inner span row.');

        self::assertInstanceOf(TimelineSpanRow::class, $outer, 'Prepared rows must be typed span rows.');
        self::assertSame(
            0,
            $outer->depth,
            'The enclosing span sits at the root, however many children it holds.',
        );
        self::assertInstanceOf(TimelineSpanRow::class, $inner, 'Prepared rows must be typed span rows.');
        self::assertSame(
            1,
            $inner->depth,
            'Indentation must follow the nested span, not its parent.',
        );
    }

    /**
     * Builds a provider whose panel reports the exact duration (bypassing hydration epoch float noise) and returns its
     * ruler ticks; `$line` of `null` exercises the default tick target.
     *
     * @return array<int, float>
     */
    private function rulersFor(float $durationMs, int|null $line = null): array
    {
        $panel = $this->stubPanel();

        $this->setInaccessibleProperty($panel, 'duration', $durationMs);

        $provider = new DataProvider($panel);

        return $line === null ? $provider->getRulers() : $provider->getRulers($line);
    }

    private function stubPanel(): TimelinePanel
    {
        $this->mockWebApplication();

        $module = new Module('debug');
        $module->logTarget = new LogTarget($module);

        Yii::$app->controller = new Controller('debug', $module);

        $panel = new TimelinePanel(['id' => 'timeline', 'module' => $module]);

        $this->hydratePanel($panel, new TimelineSnapshot(1_700_000_000.0, 1_700_000_000.1, 1_048_576));

        return $panel;
    }
}
