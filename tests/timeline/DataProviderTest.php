<?php

declare(strict_types=1);

namespace yii\debug\tests\timeline;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\{LogTarget, Module};
use yii\debug\models\timeline\DataProvider;
use yii\debug\panels\TimelinePanel;
use yii\debug\tests\support\TestCase;
use yii\web\Controller;

/**
 * Unit tests for {@see DataProvider} covering the color-bucket fallback, the CSS alignment class, the memory tuple,
 * the adaptive ruler ticks, and the `cssNumber` non-numeric guard.
 */
#[Group('timeline')]
final class DataProviderTest extends TestCase
{
    public function testCssNumberFallsBackToNullForNonNumericValues(): void
    {
        $panel = $this->stubPanel();
        $provider = new DataProvider($panel);

        // 'css.width' is a string here → 'cssNumber' falls back to `null` → 'getColor' fetches width via `getWidth`.
        $result = $provider->getColor(['css' => ['width' => 'not-a-number'], 'duration' => 0.05]);

        self::assertStringStartsWith(
            '#',
            $result,
            "Non-numeric 'css.width' must fall back through 'getWidth' and still produce a hex color.",
        );
    }

    public function testGetColorReturnsFallbackWhenNoBucketMatches(): void
    {
        $panel = $this->stubPanel();

        $provider = new DataProvider($panel);

        // Force the bucket lookup to miss by passing a 'css.width' of `-1` (below every configured threshold).
        self::assertSame(
            '#d6e685',
            $provider->getColor(['css' => ['width' => -1.0]]),
            'Sub-threshold widths must surface the fallback green hex.',
        );
    }

    public function testGetCssClassAlignsLeftForLeftBars(): void
    {
        $panel = $this->stubPanel();

        $provider = new DataProvider($panel);

        $result = $provider->getCssClass(['css' => ['left' => 0.0, 'width' => 30.0]]);

        self::assertStringContainsString(
            'left',
            $result,
            'Bars sitting before the 15% threshold must align left.',
        );
    }

    public function testGetCssClassAlignsRightForFarRightBars(): void
    {
        $panel = $this->stubPanel();

        $provider = new DataProvider($panel);

        $result = $provider->getCssClass(['css' => ['left' => 60.0, 'width' => 30.0]]);

        self::assertStringContainsString(
            'right',
            $result,
            "Bars sitting beyond the 50% midpoint must align 'right' to avoid label clipping.",
        );
    }

    public function testGetMemoryReturnsFormattedTupleForPositiveValue(): void
    {
        $panel = $this->stubPanel();

        $provider = new DataProvider($panel);

        $tuple = $provider->getMemory(['memory' => 1_048_576]);

        self::assertIsArray(
            $tuple,
            "Numeric memory must yield a '[formatted, y]' tuple.",
        );
        self::assertStringContainsString(
            'MB',
            $tuple[0],
            "Formatted slot must surface the 'MB' suffix.",
        );
    }

    public function testGetMemoryReturnsNullForNonNumericValue(): void
    {
        $panel = $this->stubPanel();

        $provider = new DataProvider($panel);

        self::assertNull(
            $provider->getMemory(['memory' => 'not-numeric']),
            "Non-numeric memory must collapse to 'null'.",
        );
    }

    public function testGetMemoryReturnsNullForNonPositiveValue(): void
    {
        $panel = $this->stubPanel();

        $provider = new DataProvider($panel);

        self::assertNull(
            $provider->getMemory(['memory' => 0]),
            "Zero or negative memory must collapse to 'null'.",
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

    public function testPrepareModelsTracksChildOverlap(): void
    {
        $panel = $this->stubPanel();

        $provider = new DataProvider(
            $panel,
            [
                'allModels' => [
                    ['category' => 'outer', 'timestamp' => 0.0, 'duration' => 0.05],
                    ['category' => 'inner', 'timestamp' => 0.001, 'duration' => 0.02],
                ],
            ],
        );

        $models = $provider->getModels();

        self::assertCount(
            2,
            $models,
            'Both rows must be prepared.',
        );
        self::assertArrayHasKey(
            0,
            $models,
            'Prepared models must expose the first slot.',
        );

        $outer = $models[0];

        self::assertIsArray(
            $outer,
            'Prepared rows must be arrays.',
        );
        self::assertSame(
            1,
            $outer['child'] ?? 0,
            'Outer span overlapping the inner span must record one child.',
        );
    }

    /**
     * Builds a provider whose panel reports the exact duration (bypassing `load()` epoch float noise) and returns its
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

        $panel->load(['start' => 1_700_000_000.0, 'end' => 1_700_000_000.1, 'memory' => 1_048_576]);

        return $panel;
    }
}
