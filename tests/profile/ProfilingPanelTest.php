<?php

declare(strict_types=1);

namespace yii\debug\tests\profile;

use PHPForge\Debug\Storage\ExceptionSnapshot;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use yii\debug\panels\profile\ProfilingSnapshot;
use yii\debug\panels\ProfilingPanel;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

/**
 * Unit tests for {@see ProfilingPanel} covering the profile capture, the typed row decoration, the toolbar items
 * (time + memory), the title-blanking on the toolbar payload, and snapshot hydration.
 */
#[Group('panel')]
#[Group('profile')]
final class ProfilingPanelTest extends TestCase
{
    public function testCaptureReturnsTypedPayloadWithMemoryAndTime(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $snapshot = $panel->capture();

        self::assertGreaterThan(
            0,
            $snapshot->memory,
            "'memory' must reflect a positive peak.",
        );
        self::assertGreaterThanOrEqual(
            0.0,
            $snapshot->time,
            "'time' must be non-negative.",
        );
        self::assertSame(
            [],
            $snapshot->entries(),
            'Empty log target yields no profile blocks.',
        );
        self::assertSame(
            [],
            $snapshot->samples(),
            'Empty log target yields no memory samples.',
        );
    }

    public function testCaptureScalesMemorySampleTimeToMilliseconds(): void
    {
        $snapshot = ProfilingSnapshot::capture(
            0,
            0.0,
            [['sample', Logger::LEVEL_INFO, 'application', 1.25, [], 2_048]],
        );

        $samples = $snapshot->samples();

        self::assertCount(
            1,
            $samples,
            'A logger tuple with time and memory must produce one sample.',
        );
        self::assertSame(
            1_250.0,
            $samples[0]->time,
            'Sample timestamps must be converted to milliseconds.',
        );
        self::assertSame(
            2_048,
            $samples[0]->memory,
            'Sample memory must retain the logger value.',
        );
    }

    public function testGetDetailFallsBackToHashTimelineUrlWhenModuleIsMissing(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $panel->module = null;

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(0, 0.0, []),
        );

        self::assertNotEmpty(
            $panel->getDetail(),
            'Missing module must still produce markup with a placeholder timeline link.',
        );
    }

    public function testGetDetailRendersWithCapturedMessages(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(
                1_048_576,
                0.123,
                [
                    ['app\\token', Logger::LEVEL_PROFILE_BEGIN, 'application', 0.0, []],
                    ['app\\token', Logger::LEVEL_PROFILE_END, 'application', 0.5, []],
                ],
            ),
        );

        self::assertNotEmpty(
            $panel->getDetail(),
            'Detail view must produce markup.',
        );
    }

    public function testGetModelsBuildsTypedRowsFromTimings(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(
                0,
                0.0,
                [
                    ['app\\sql', Logger::LEVEL_PROFILE_BEGIN, 'application', 0.0, []],
                    ['app\\sql', Logger::LEVEL_PROFILE_END, 'application', 0.005, []],
                ],
            ),
        );

        $models = $panel->getModels();

        self::assertCount(
            1,
            $models,
            'Paired begin/end must yield one row.',
        );

        $row = $models[0];

        self::assertSame(
            'app\\sql',
            $row->info,
            "'info' must round-trip from the begin token.",
        );
        self::assertSame(
            0,
            $row->seq,
            "First row must carry 'seq = 0'.",
        );
    }

    public function testGetModelsCachesTheResult(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(0, 0.0, []),
        );

        $first = $this->invoke(
            $panel,
            'getModels',
        );
        $second = $this->invoke(
            $panel,
            'getModels',
        );

        self::assertSame(
            $first,
            $second,
            'Cache must return the same list.',
        );
    }

    public function testGetNameAndIcon(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        self::assertSame(
            'Profiling',
            $panel->getName(),
            "Display name must be 'Profiling'.",
        );
        self::assertSame(
            'profiling',
            $panel->getToolbarIcon(),
            "Icon key must be 'profiling'.",
        );
    }

    public function testGetToolbarDataBlanksTitleOnSuccess(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(0, 0.0, []),
        );

        $payload = $panel->getToolbarData();

        self::assertSame(
            '',
            $payload['title'] ?? null,
            'Success path must blank the title.',
        );
    }

    public function testGetToolbarDataKeepsTitleOnError(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $panel->setError(ExceptionSnapshot::fromThrowable(new RuntimeException('boom')));

        $payload = $panel->getToolbarData();

        self::assertSame(
            'Profiling',
            $payload['title'] ?? null,
            'Error path must keep the panel title.',
        );
    }

    public function testGetToolbarItemsCarryNoStatusVerdict(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(2_097_152, 0.25, []),
        );

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Items must be a list.',
        );

        foreach ($items as $item) {
            self::assertIsArray(
                $item,
                'Each chip must be an array.',
            );
            self::assertArrayNotHasKey(
                'status',
                $item,
                'Metrics must render as neutral readouts.',
            );
        }
    }

    public function testGetToolbarItemsEmitsTimeAndMemoryChips(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(2_097_152, 0.25, []),
        );

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Items must be a list.',
        );
        self::assertCount(
            2,
            $items,
            'Toolbar must surface two chips (time + memory).',
        );

        $time = $items[0] ?? self::fail('Expected the time chip.');
        $memory = $items[1] ?? self::fail('Expected the memory chip.');

        self::assertIsArray(
            $time,
            'Time chip must be an array.',
        );
        self::assertIsArray(
            $memory,
            'Memory chip must be an array.',
        );
        self::assertSame(
            'Total processing time',
            $time['title'] ?? null,
            "Time chip must carry the 'Total' title.",
        );
        self::assertSame(
            'Peak memory',
            $memory['title'] ?? null,
            "Memory chip must carry the 'Peak' title.",
        );
    }
}
