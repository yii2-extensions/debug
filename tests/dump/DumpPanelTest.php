<?php

declare(strict_types=1);

namespace yii\debug\tests\dump;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\LogTarget;
use yii\debug\panels\{DumpPanel, RouterPanel};
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

use function is_string;

/**
 * Unit tests for {@see DumpPanel} covering the trace-log capture, the typed dump-row narrowing, the toolbar item
 * shortcut, the `varDump()` rendering pipeline (callback / highlighted / plain), and the rendered detail/summary views.
 */
#[Group('panel')]
#[Group('dump')]
final class DumpPanelTest extends TestCase
{
    public function testGetDetailRendersWithCapturedMessages(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $panel->data = [
            ['<pre>42</pre>', Logger::LEVEL_TRACE, 'application', 0.001, []],
        ];

        self::assertNotEmpty(
            $panel->getDetail(),
            'Detail view must produce markup.',
        );
    }

    public function testGetModelsCachesNormalizedRows(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $panel->data = [
            ['<pre>42</pre>', Logger::LEVEL_TRACE, 'application', 0.5, []],
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

    public function testGetModelsRebuildsCacheWhenRefreshIsTrue(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $panel->data = [
            ['a', Logger::LEVEL_TRACE, 'application', 0.0, []],
        ];

        $first = $this->invoke(
            $panel,
            'getModels',
        );

        self::assertIsArray(
            $first,
            'Models must be an array.',
        );
        self::assertCount(
            1,
            $first,
            'Single message must yield one row.',
        );

        $panel->data = [
            ['a', Logger::LEVEL_TRACE, 'application', 0.0, []],
            ['b', Logger::LEVEL_TRACE, 'application', 0.0, []],
        ];

        $refreshed = $this->invoke(
            $panel,
            'getModels',
            [true],
        );

        self::assertIsArray(
            $refreshed,
            'Refreshed models must be an array.',
        );
        self::assertCount(
            2,
            $refreshed,
            'Refresh must rebuild from the latest data.',
        );
    }

    public function testGetModelsScalesTimeToMilliseconds(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $panel->data = [
            ['msg', Logger::LEVEL_TRACE, 'application', 2.5, []],
        ];

        $models = $this->invoke(
            $panel,
            'getModels',
        );

        self::assertIsArray(
            $models,
            'Models must be an array.',
        );

        $row = $models[0] ?? self::fail('Expected one row.');

        self::assertIsArray(
            $row,
            'Row must be an array.',
        );
        self::assertEqualsWithDelta(
            2500.0,
            $row['time'] ?? null,
            1e-9,
            'Time must be scaled to milliseconds.',
        );
    }

    public function testGetModelsSkipsEntriesThatAreNotArrays(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $this->setInaccessibleProperty(
            $panel,
            'data',
            [
                ['valid', Logger::LEVEL_TRACE, 'application', 0.0, []],
                'invalid-string-entry',
            ],
        );

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
            'Non-array entries must be dropped.',
        );
    }

    public function testGetModelsTreatsNonArrayDataAsEmpty(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $this->setInaccessibleProperty(
            $panel,
            'data',
            'not-an-array',
        );

        $models = $this->invoke(
            $panel,
            'getModels',
        );

        self::assertSame(
            [],
            $models,
            'Corrupt data must collapse to no rows.',
        );
    }

    public function testGetNameAndIcon(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        self::assertSame(
            'Dump',
            $panel->getName(),
            "Display name must be 'Dump'.",
        );
        self::assertSame(
            'dump',
            $panel->getToolbarIcon(),
            "Icon key must be 'dump'.",
        );
    }

    public function testGetSummaryRendersChipWhenMessagesPresent(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $panel->data = [
            ['a', Logger::LEVEL_TRACE, 'application', 0.0, []],
        ];

        self::assertStringContainsString(
            'Dump',
            $panel->getSummary(),
            'Chip must render the panel label.',
        );
    }

    public function testGetSummaryReturnsEmptyMarkupWhenNoMessages(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        self::assertSame(
            '',
            $panel->getSummary(),
            'No data means no toolbar chip.',
        );
    }

    public function testGetToolbarItemsEmitsCountChipWhenMessagesPresent(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $panel->data = [
            ['a', Logger::LEVEL_TRACE, 'application', 0.0, []],
            ['b', Logger::LEVEL_TRACE, 'application', 0.0, []],
        ];

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Items must be a list.',
        );

        $first = $items[0] ?? self::fail('Expected one item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            'info',
            $first['status'] ?? null,
            "Chip status must be 'info'.",
        );
        self::assertSame(
            2,
            $first['value'] ?? null,
            'Value must match the message count.',
        );
    }

    public function testGetToolbarItemsReturnsNullWhenDataIsEmpty(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        self::assertNull(
            $this->invoke(
                $panel,
                'getToolbarItems',
            ),
            'Empty data must skip the toolbar item.',
        );
    }

    public function testGetToolbarItemsReturnsNullWhenDataIsNotArray(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $this->setInaccessibleProperty(
            $panel,
            'data',
            'corrupt',
        );

        self::assertNull(
            $this->invoke(
                $panel,
                'getToolbarItems',
            ),
            'Non-array data must skip the toolbar item.',
        );
    }

    public function testNormalizeStringListDropsNonStringEntriesAndFallsBackOnNonArray(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        self::assertSame(
            ['kept-a', 'kept-b'],
            $this->invoke(
                $panel,
                'normalizeStringList',
                [['kept-a', 42, null, 'kept-b']],
            ),
            'Only string entries must survive.',
        );
        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'normalizeStringList',
                ['not-an-array'],
            ),
            'Non-array input must collapse to `[]`.',
        );
    }

    public function testSaveAppliesVarDumpToEachMessageHead(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $panel->highlight = false;

        $this->logTargetOf($panel)->messages = [
            [['stringValue'], Logger::LEVEL_TRACE, 'application', 0.0, [], 0],
        ];

        $saved = $panel->save();

        $first = $saved[0] ?? self::fail('Expected one captured message.');

        self::assertIsString(
            $first[0] ?? null,
            'First slot must be a dumped string.',
        );
        self::assertStringContainsString(
            'stringValue',
            $first[0],
            'Dumped output must contain the value.',
        );
    }

    public function testSaveSkipsCategoriesOwnedByRouterPanel(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $panel->highlight = false;
        $panel->categories = [];

        $module = $panel->module ?? self::fail('Module must be wired.');
        $module->panels['router'] = new RouterPanel(['id' => 'router', 'module' => $module]);

        $this->logTargetOf($panel)->messages = [
            ['kept', Logger::LEVEL_TRACE, 'application', 0.0, [], 0],
            ['dropped', Logger::LEVEL_TRACE, 'yii\\web\\UrlManager::parseRequest', 0.0, [], 0],
        ];

        $saved = $panel->save();

        self::assertCount(
            1,
            $saved,
            'Router categories must be filtered.',
        );
    }

    public function testSaveSkipsMessagesWithoutFirstSlot(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $panel->highlight = false;

        $this->logTargetOf($panel)->messages = [
            [1 => Logger::LEVEL_TRACE, 2 => 'application', 3 => 0.0, 4 => [], 5 => 0],
        ];

        $saved = $panel->save();

        $first = $saved[0] ?? self::fail('Expected one captured message.');

        self::assertArrayNotHasKey(
            0,
            $first,
            'Missing first slot must be left untouched.',
        );
    }

    public function testVarDumpDelegatesToCallbackWhenSet(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $panel->varDumpCallback = static fn(
            mixed $value,
            DumpPanel $panel,
        ): string => 'custom:' . (is_string($value) ? $value : 'other');

        self::assertSame(
            'custom:hello',
            $panel->varDump('hello'),
            'Callback output must round-trip.',
        );
    }

    public function testVarDumpEncodesPlainOutputWhenHighlightIsFalse(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $panel->highlight = false;

        $dump = $panel->varDump('<script>');

        self::assertStringContainsString(
            '&lt;script&gt;',
            $dump,
            "Plain mode must HTML-escape '<script>'.",
        );
        self::assertStringNotContainsString(
            '<script>',
            $dump,
            'Plain mode must not leak raw HTML.',
        );
    }

    public function testVarDumpKeepsHighlightedOutputUnchanged(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $panel->highlight = true;

        $dump = $panel->varDump('value');

        self::assertStringContainsString(
            '<',
            $dump,
            'Highlighted output must contain markup.',
        );
    }

    /**
     * Resolves the typed {@see \yii\debug\LogTarget} from a panel built by {@see TestCase::makePanel()}, narrowing the
     * loose `LogTarget|array|string` declared on `\yii\debug\Module::$logTarget`.
     */
    private function logTargetOf(DumpPanel $panel): LogTarget
    {
        $logTarget = $panel->module?->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            'Log target must be wired.',
        );

        return $logTarget;
    }
}
