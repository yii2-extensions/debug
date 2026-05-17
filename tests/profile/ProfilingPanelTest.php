<?php

declare(strict_types=1);

namespace yii\debug\tests\profile;

use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use yii\debug\FlattenException;
use yii\debug\panels\ProfilingPanel;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

/**
 * Unit tests for {@see ProfilingPanel} covering the profile capture, the typed row decoration, the toolbar items
 * (time + memory), the title-blanking on the toolbar payload, and the saved-payload narrowing.
 */
#[Group('panel')]
#[Group('profile')]
final class ProfilingPanelTest extends TestCase
{
    public function testGetDetailFallsBackToHashTimelineUrlWhenModuleIsMissing(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $panel->module = null;

        $panel->data = [
            'memory' => 0,
            'time' => 0.0,
            'messages' => [],
        ];

        self::assertNotEmpty(
            $panel->getDetail(),
            'Missing module must still produce markup with a placeholder timeline link.',
        );
    }

    public function testGetDetailRendersWithCapturedMessages(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $panel->data = [
            'memory' => 1_048_576,
            'time' => 0.123,
            'messages' => [
                ['app\\token', Logger::LEVEL_PROFILE_BEGIN, 'application', 0.0, []],
                ['app\\token', Logger::LEVEL_PROFILE_END, 'application', 0.5, []],
            ],
        ];

        self::assertNotEmpty(
            $panel->getDetail(),
            'Detail view must produce markup.',
        );
    }

    public function testGetModelsBuildsTypedRowsFromTimings(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $panel->data = [
            'memory' => 0,
            'time' => 0.0,
            'messages' => [
                ['app\\sql', Logger::LEVEL_PROFILE_BEGIN, 'application', 0.0, []],
                ['app\\sql', Logger::LEVEL_PROFILE_END, 'application', 0.005, []],
            ],
        ];

        $models = $this->invoke(
            $panel,
            'getModels',
        );

        self::assertIsArray(
            $models,
            'Models must be an array.',
        );
        self::assertCount(
            1,
            $models,
            'Paired begin/end must yield one row.',
        );

        $row = $models[0] ?? self::fail('Expected one row.');

        self::assertIsArray(
            $row,
            'Row must be an array.',
        );
        self::assertSame(
            'app\\sql',
            $row['info'] ?? null,
            "'info' must round-trip from the begin token.",
        );
        self::assertSame(
            0,
            $row['seq'] ?? null,
            "First row must carry 'seq = 0'.",
        );
    }

    public function testGetModelsCachesTheResult(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $panel->data = [
            'memory' => 0,
            'time' => 0.0,
            'messages' => [],
        ];

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

    public function testGetProfileDataCollapsesNonArrayDataToDefaults(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $this->setInaccessibleProperty(
            $panel,
            'data',
            'corrupt',
        );

        $profileData = $this->invoke(
            $panel,
            'getProfileData',
        );

        self::assertIsArray(
            $profileData,
            'Profile data must be an array.',
        );
        self::assertSame(
            0,
            $profileData['memory'] ?? null,
            "Missing memory must default to '0'.",
        );
        self::assertSame(
            0.0,
            $profileData['time'] ?? null,
            "Missing time must default to '0.0'.",
        );
        self::assertSame(
            [],
            $profileData['messages'] ?? null,
            "Missing messages must default to '[]'.",
        );
    }

    public function testGetProfileDataExtractsTypedSlotsFromValidData(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $panel->data = [
            'memory' => 1024,
            'time' => 0.5,
            'messages' => [
                ['x', Logger::LEVEL_PROFILE_BEGIN, 'app', 0.0, []],
            ],
        ];

        $profileData = $this->invoke(
            $panel,
            'getProfileData',
        );

        self::assertIsArray(
            $profileData,
            'Profile data must be an array.',
        );
        self::assertSame(
            1024,
            $profileData['memory'] ?? null,
            'Memory must round-trip from saved data.',
        );
        self::assertEqualsWithDelta(
            0.5,
            $profileData['time'] ?? null,
            1e-9,
            'Time must round-trip from saved data.',
        );
    }

    public function testGetSummaryRendersChip(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $panel->data = [
            'memory' => 1024,
            'time' => 0.1,
            'messages' => [],
        ];

        self::assertNotEmpty(
            $panel->getSummary(),
            'Summary chip must produce markup.',
        );
    }

    public function testGetToolbarDataBlanksTitleOnSuccess(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $panel->data = [
            'memory' => 0,
            'time' => 0.0,
            'messages' => [],
        ];

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

        $panel->setError(new FlattenException(new RuntimeException('boom')));

        $payload = $panel->getToolbarData();

        self::assertSame(
            'Profiling',
            $payload['title'] ?? null,
            'Error path must keep the panel title.',
        );
    }

    public function testGetToolbarItemsEmitsTimeAndMemoryChips(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $panel->data = [
            'memory' => 2_097_152,
            'time' => 0.25,
            'messages' => [],
        ];

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

    public function testNormalizeMessagesDropsNonArrayEntries(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $normalized = $this->invoke(
            $panel,
            'normalizeMessages',
            [
                [
                    ['valid', Logger::LEVEL_PROFILE_BEGIN, 'app', 0.0, []],
                    'invalid-string',
                ],
            ],
        );

        self::assertIsArray(
            $normalized,
            'Normalized must be an array.',
        );
        self::assertCount(
            1,
            $normalized,
            'Non-array entries must be dropped.',
        );
    }

    public function testNormalizeMessagesReturnsEmptyForNonArrayInput(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'normalizeMessages',
                ['not-an-array'],
            ),
            "Non-array input must collapse to '[]'.",
        );
    }

    public function testNormalizeTimingFillsDefaultsForOptionalFields(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $row = $this->invoke(
            $panel,
            'normalizeTiming',
            [
                ['info' => 'block', 'duration' => 0.5, 'timestamp' => 1.0],
                7,
            ],
        );

        self::assertIsArray(
            $row,
            'Valid timing must yield a row.',
        );
        self::assertSame(
            'block',
            $row['info'] ?? null,
            "'info' must round-trip.",
        );
        self::assertSame(
            7,
            $row['seq'] ?? null,
            "'seq' must reflect the provided index.",
        );
        self::assertSame(
            '',
            $row['category'] ?? null,
            "Missing 'category' must collapse to ''.",
        );
        self::assertEqualsWithDelta(
            500.0,
            $row['duration'] ?? null,
            1e-9,
            "'duration' must be scaled to ms.",
        );
        self::assertEqualsWithDelta(
            1000.0,
            $row['timestamp'] ?? null,
            1e-9,
            "'timestamp' must be scaled to ms.",
        );
    }

    public function testNormalizeTimingReturnsNullForIncompletePayloads(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        self::assertNull(
            $this->invoke($panel, 'normalizeTiming', ['not an array', 0]),
            "Non-array timing must yield 'null'.",
        );
        self::assertNull(
            $this->invoke($panel, 'normalizeTiming', [['timestamp' => 0.0], 0]),
            "Missing 'duration' must yield 'null'.",
        );
        self::assertNull(
            $this->invoke($panel, 'normalizeTiming', [['duration' => 0.0], 0]),
            "Missing 'timestamp' must yield 'null'.",
        );
    }

    public function testSaveReturnsTypedPayloadWithMemoryAndTime(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $payload = $panel->save();

        self::assertGreaterThan(
            0,
            $payload['memory'],
            "'memory' must reflect a positive peak.",
        );
        self::assertGreaterThanOrEqual(
            0.0,
            $payload['time'],
            "'time' must be non-negative.",
        );
        self::assertSame(
            [],
            $payload['messages'],
            'Empty log target yields no profile messages.',
        );
    }
}
