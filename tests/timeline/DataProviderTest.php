<?php

declare(strict_types=1);

namespace yii\debug\tests\timeline;

use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Yii;
use yii\debug\{LogTarget, Module};
use yii\debug\models\timeline\DataProvider;
use yii\debug\panels\TimelinePanel;
use yii\debug\tests\support\TestCase;
use yii\web\Controller;

/**
 * Unit tests for {@see DataProvider} covering the color-bucket fallback, the CSS alignment class, the memory tuple,
 * the ruler-tick disable branch, the orphan-panel error path, and the `cssNumber` non-numeric guard.
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

    public function testThrowRuntimeExceptionWhenPanelClearedAfterConstruction(): void
    {
        $panel = $this->stubPanel();
        $provider = new DataProvider($panel);

        $this->setInaccessibleProperty($provider, 'panel', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'TimelinePanel has not been set on the data provider.',
        );

        $provider->getRulers();
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
