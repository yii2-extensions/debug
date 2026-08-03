<?php

declare(strict_types=1);

namespace yii\debug\tests\dump;

use PHPUnit\Framework\Attributes\Group;
use yii\base\InvalidConfigException;
use yii\debug\LogTarget;
use yii\debug\panels\dump\DumpSnapshot;
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
    public function testCaptureAppliesVarDumpToEachMessageHead(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $panel->highlight = false;

        $this->logTargetOf($panel)->messages = [
            [['stringValue'], Logger::LEVEL_TRACE, 'application', 0.0, [], 0],
        ];

        $first = $panel->capture()->entries()[0] ?? self::fail('Expected one captured row.');

        self::assertStringContainsString(
            'stringValue',
            $first->message,
            'Dumped output must contain the value.',
        );
    }

    public function testCaptureReportsMissingModuleThroughThePanelContract(): void
    {
        $panel = new DumpPanel();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'The debug module logTarget must be initialized before reading log messages.',
        );

        $panel->capture();
    }

    public function testCaptureSkipsCategoriesOwnedByRouterPanel(): void
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

        self::assertCount(
            1,
            $panel->capture()->entries(),
            'Router categories must be filtered.',
        );
    }

    public function testCaptureSkipsMessagesWithoutFirstSlot(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $panel->highlight = false;

        $this->logTargetOf($panel)->messages = [
            [1 => Logger::LEVEL_TRACE, 2 => 'application', 3 => 0.0, 4 => [], 5 => 0],
            [['later-value'], Logger::LEVEL_TRACE, 'application', 1.0, [], 0],
        ];

        $entries = $panel->capture()->entries();

        $first = $entries[0] ?? self::fail('Expected the malformed captured row.');
        $second = $entries[1] ?? self::fail('Expected the later valid captured row.');

        self::assertSame(
            '',
            $first->message,
            'Missing first slot must collapse to an empty message.',
        );
        self::assertStringContainsString(
            'later-value',
            $second->message,
            'A malformed message must not stop later payloads from being rendered.',
        );
    }

    public function testGetDetailRendersEmptyStateWhenNoDumpsCaptured(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $this->hydratePanel($panel, DumpSnapshot::capture([]));

        $detail = $panel->getDetail();

        self::assertStringContainsString(
            'yii-debug-empty-state',
            $detail,
            'Empty capture must render the empty-state card.',
        );
        self::assertStringContainsString(
            'No variables dumped in this request',
            $detail,
            'Empty-state heading must explain the missing dumps.',
        );
    }

    public function testGetDetailRendersWithCapturedMessages(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $this->hydratePanel(
            $panel,
            DumpSnapshot::capture(
                [
                    ['<pre>42</pre>', Logger::LEVEL_TRACE, 'application', 0.001, []],
                ],
            ),
        );

        $detail = $panel->getDetail();

        self::assertNotEmpty(
            $detail,
            'Detail view must produce markup.',
        );
        self::assertStringContainsString(
            'yii-debug-grid-dump',
            $detail,
            'Grid must carry the dump variant class.',
        );
    }

    public function testGetModelsCachesNormalizedRows(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $this->hydratePanel(
            $panel,
            DumpSnapshot::capture(
                [
                    ['<pre>42</pre>', Logger::LEVEL_TRACE, 'application', 0.5, []],
                ],
            ),
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

    public function testGetModelsRebuildsCacheWhenRefreshIsTrue(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $this->hydratePanel(
            $panel,
            DumpSnapshot::capture(
                [
                    ['a', Logger::LEVEL_TRACE, 'application', 0.0, []],
                ],
            ),
        );

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

        $this->hydratePanel(
            $panel,
            DumpSnapshot::capture(
                [
                    ['a', Logger::LEVEL_TRACE, 'application', 0.0, []],
                    ['b', Logger::LEVEL_TRACE, 'application', 0.0, []],
                ],
            ),
        );

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

        $this->hydratePanel(
            $panel,
            DumpSnapshot::capture(
                [
                    ['msg', Logger::LEVEL_TRACE, 'application', 2.5, []],
                ],
            ),
        );

        $row = $panel->getDumps()[0] ?? self::fail('Expected one row.');

        self::assertEqualsWithDelta(
            2500.0,
            $row->time,
            1e-9,
            'Time must be scaled to milliseconds.',
        );
    }

    public function testGetModelsSkipsEntriesThatAreNotArrays(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $this->hydratePanel(
            $panel,
            DumpSnapshot::capture(
                [
                    ['valid', Logger::LEVEL_TRACE, 'application', 0.0, []],
                    'invalid-string-entry',
                ],
            ),
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

    public function testGetToolbarItemsEmitsCountChipWhenMessagesPresent(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $this->hydratePanel(
            $panel,
            DumpSnapshot::capture(
                [
                    ['a', Logger::LEVEL_TRACE, 'application', 0.0, []],
                    ['b', Logger::LEVEL_TRACE, 'application', 0.0, []],
                ],
            ),
        );

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

    public function testGetToolbarItemsReturnsEmptyArrayWhenDataIsEmpty(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'getToolbarItems',
            ),
            'Empty data must skip the toolbar item.',
        );
    }

    public function testGetToolbarItemsReturnsEmptyArrayWhenDataIsNotArray(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'getToolbarItems',
            ),
            'Non-array data must skip the toolbar item.',
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
