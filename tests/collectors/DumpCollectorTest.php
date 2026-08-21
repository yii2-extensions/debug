<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use PHPForge\Debug\Panel\Dump\DumpRow;
use PHPUnit\Framework\Attributes\Group;
use yii\base\InvalidConfigException;
use yii\debug\collectors\DumpCollector;
use yii\debug\{LogTarget, Module};
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

use function is_string;

/**
 * Unit tests for {@see DumpCollector} covering the trace-log capture, the router-category exclusion, the `varDump()`
 * rendering pipeline (callback / highlighted / plain), and the startup/shutdown lifecycle.
 */
#[Group('collector')]
#[Group('dump')]
final class DumpCollectorTest extends TestCase
{
    public function testCaptureAppliesVarDumpToEachMessageHead(): void
    {
        $collector = $this->makeCollector();

        $collector->varDumpCallback = static fn(mixed $value, DumpCollector $collector): string => 'formatted';

        $this->logTargetOf($collector)->messages = [
            [['stringValue'], Logger::LEVEL_TRACE, 'application', 0.0, [], 0],
        ];

        $first = $this->captureEntries($collector)[0] ?? self::fail('Expected one captured row.');

        self::assertSame(
            'formatted',
            $first->message,
            'Capture must use the Dump-specific formatter.',
        );
    }

    public function testCaptureReturnsNullAfterShutdown(): void
    {
        $collector = $this->makeCollector();

        $collector->shutdown();

        self::assertNull(
            $collector->capture(),
            'Stopped collector must record nothing.',
        );
    }

    public function testCaptureReturnsNullBeforeStartup(): void
    {
        self::assertNull(
            (new DumpCollector())->capture(),
            'Idle collector must record nothing.',
        );
    }

    public function testCaptureSkipsCategoriesOwnedByRouterCollector(): void
    {
        $collector = $this->makeCollector();

        $collector->highlight = false;
        $collector->categories = [];

        $this->logTargetOf($collector)->messages = [
            ['kept', Logger::LEVEL_TRACE, 'application', 0.0, [], 0],
            ['dropped', Logger::LEVEL_TRACE, 'yii\\web\\UrlManager::parseRequest', 0.0, [], 0],
        ];

        self::assertCount(
            1,
            $this->captureEntries($collector),
            'Router categories must be filtered.',
        );
    }

    public function testIdPairsWithTheDumpPanel(): void
    {
        self::assertSame(
            'dump',
            (new DumpCollector())->id(),
            "Stable ID must be 'dump'.",
        );
    }

    public function testThrowInvalidConfigExceptionWhenModuleLogTargetIsMissing(): void
    {
        $collector = new DumpCollector();

        $collector->startup();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'The debug module logTarget must be initialized before reading log messages.',
        );

        $collector->capture();
    }

    public function testVarDumpDelegatesToCallbackWhenSet(): void
    {
        $collector = $this->makeCollector();

        $collector->varDumpCallback = static fn(
            mixed $value,
            DumpCollector $collector,
        ): string => 'custom:' . (is_string($value) ? $value : 'other');

        self::assertSame(
            'custom:hello',
            $collector->varDump('hello'),
            'Callback output must round-trip.',
        );
    }

    public function testVarDumpEncodesCustomCallbackOutput(): void
    {
        $collector = $this->makeCollector();

        $collector->varDumpCallback = static fn(mixed $value, DumpCollector $collector): string
            => '<script>alert(1)</script>';

        self::assertSame(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            $collector->varDump('ignored'),
            'Custom callback output must be treated as untrusted text.',
        );
    }

    public function testVarDumpEncodesPlainOutputWhenHighlightIsFalse(): void
    {
        $collector = $this->makeCollector();

        $collector->highlight = false;

        $dump = $collector->varDump('<script>');

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
        $collector = $this->makeCollector();

        $collector->highlight = true;

        $dump = $collector->varDump('value');

        self::assertStringContainsString(
            '<',
            $dump,
            'Highlighted output must contain markup.',
        );
    }

    /**
     * Captures the dump rows, failing when the started collector produces no snapshot.
     *
     * @param DumpCollector $collector Started collector.
     *
     * @return list<DumpRow> Captured dump rows.
     */
    private function captureEntries(DumpCollector $collector): array
    {
        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'Started collector must capture a snapshot.',
        );

        return $snapshot->entries();
    }

    /**
     * Resolves the typed {@see LogTarget} from the collector's module, narrowing the loose `LogTarget|array|string`
     * declared on {@see Module::$logTarget}.
     */
    private function logTargetOf(DumpCollector $collector): LogTarget
    {
        $logTarget = $collector->module?->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            'Log target must be wired.',
        );

        return $logTarget;
    }

    /**
     * Creates a started collector wired to a debug module on top of a mocked web application.
     *
     * @return DumpCollector Started collector.
     */
    private function makeCollector(): DumpCollector
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        $module->logTarget = new LogTarget($module);

        $collector = new DumpCollector();

        $collector->module = $module;

        $collector->startup();

        return $collector;
    }
}
