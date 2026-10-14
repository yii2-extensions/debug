<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use PHPForge\Debug\Helper\SensitiveDataRedactor;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\collectors\LogCollector;
use yii\debug\{LogTarget, Module};
use yii\debug\tests\support\Captured;
use yii\debug\tests\support\TestCase;
use yii\log\{Logger, PsrMessage};

/**
 * Unit tests for {@see LogCollector} covering the log capture, the PSR-3 context capture, the router-category exclusion,
 * and the startup/shutdown lifecycle.
 */
#[Group('collector')]
#[Group('log')]
final class LogCollectorTest extends TestCase
{
    public function testCaptureExcludesCategoriesOwnedByRouterCollector(): void
    {
        $collector = $this->makeCollector();

        $logTarget = $collector->module?->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            'Log target must be wired.',
        );

        $logTarget->messages = [
            ['kept', Logger::LEVEL_INFO, 'application', 0.0, [], 0],
            ['dropped', Logger::LEVEL_TRACE, 'yii\\web\\UrlManager::parseRequest', 0.0, [], 0],
        ];

        $snapshot = Captured::log($collector);

        self::assertNotNull(
            $snapshot,
            'Started collector must capture a snapshot.',
        );
        self::assertCount(
            1,
            $snapshot->entries(),
            'Router categories must be filtered.',
        );
    }

    public function testCaptureKeepsEmptyContextForPlainMessages(): void
    {
        $collector = $this->makeCollector();

        $logTarget = $collector->module?->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            'Log target must be wired.',
        );

        $logTarget->messages = [
            ['plain {id}', Logger::LEVEL_INFO, 'application', 0.0, [], 0],
            [['id' => 42], Logger::LEVEL_INFO, 'application', 0.0, [], 0],
        ];

        $entries = Captured::log($collector)?->entries() ?? self::fail('Expected the captured rows.');

        $string = $entries[0] ?? self::fail('Expected the string row.');
        $array = $entries[1] ?? self::fail('Expected the array row.');

        self::assertSame(
            'plain {id}',
            $string->message,
            'String message must stay verbatim.',
        );
        self::assertSame(
            [],
            $string->context,
            'String message carries no context.',
        );
        self::assertStringContainsString(
            "'id' => 42",
            $array->message,
            'Array message must stay exported inline.',
        );
        self::assertSame(
            [],
            $array->context,
            'Array message must not be read as a context.',
        );
    }

    public function testCaptureKeepsPsrMessageContextWithoutMatchingCategory(): void
    {
        $collector = $this->makeCollector();

        $module = $collector->module;

        self::assertInstanceOf(
            Module::class,
            $module,
            'Module must be wired.',
        );

        $module->sensitiveKeys = [...SensitiveDataRedactor::DEFAULT_KEYS, 'pin'];

        $logTarget = $module->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            'Log target must be wired.',
        );

        $logTarget->messages = [
            [
                new PsrMessage(
                    'User {id} logged in',
                    [
                        'id' => 42,
                        'category' => 'audit',
                        'roles' => ['admin', 'editor'],
                        'password' => 'secret',
                        'pin' => '1234',
                    ],
                    'info',
                ),
                Logger::LEVEL_INFO,
                'audit',
                0.0,
                [],
                0,
            ],
        ];

        $row = Captured::log($collector)?->entries()[0] ?? self::fail('Expected the captured row.');

        self::assertSame(
            'User 42 logged in',
            $row->message,
            'Message must be the interpolated text.',
        );
        self::assertSame(
            [
                'id' => 42,
                'roles' => ['admin', 'editor'],
                'password' => '[redacted]',
                'pin' => '[redacted]',
            ],
            $row->context,
            'Context must drop the row-matching `category` and redact keys through the module policy.',
        );
    }

    public function testCaptureKeepsUnmatchedCategoryInPsrMessageContext(): void
    {
        $collector = $this->makeCollector();

        $logTarget = $collector->module?->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            'Log target must be wired.',
        );

        $logTarget->messages = [
            [
                new PsrMessage('Null category', ['category' => null], 'info'),
                Logger::LEVEL_INFO,
                'application',
                0.0,
                [],
                0,
            ],
            [
                new PsrMessage('Array category', ['category' => ['audit']], 'info'),
                Logger::LEVEL_INFO,
                'application',
                0.0,
                [],
                0,
            ],
            [
                new PsrMessage('String category', ['category' => 'audit'], 'info'),
                Logger::LEVEL_INFO,
                'application',
                0.0,
                [],
                0,
            ],
        ];

        $rows = Captured::log($collector)?->entries() ?? self::fail('Expected the captured rows.');

        $nullRow = $rows[0] ?? self::fail('Expected the `null` category row.');
        $arrayRow = $rows[1] ?? self::fail('Expected the array category row.');
        $stringRow = $rows[2] ?? self::fail('Expected the `string` category row.');

        self::assertSame(
            ['category' => null],
            $nullRow->context,
            'Unconsumed `null` category must stay in the context.',
        );
        self::assertSame(
            ['category' => ['audit']],
            $arrayRow->context,
            'Unconsumed array category must stay in the context.',
        );
        self::assertSame(
            ['category' => 'audit'],
            $stringRow->context,
            'Category differing from the row category must stay in the context.',
        );
    }

    public function testCaptureReturnsNullAfterShutdown(): void
    {
        $collector = $this->makeCollector();

        $collector->shutdown();

        self::assertNull(
            Captured::log($collector),
            'Stopped collector must record nothing.',
        );
    }

    public function testCaptureReturnsNullBeforeStartup(): void
    {
        self::assertNull(
            Captured::log(new LogCollector()),
            'Idle collector must record nothing.',
        );
    }

    public function testCaptureReturnsTypedRows(): void
    {
        $collector = $this->makeCollector();

        $snapshot = Captured::log($collector);

        self::assertNotNull(
            $snapshot,
            'Started collector must capture a snapshot.',
        );
        self::assertSame(
            [],
            $snapshot->entries(),
            'Empty log target yields no rows.',
        );
    }

    public function testIdPairsWithTheLogsPanel(): void
    {
        self::assertSame(
            'log',
            (new LogCollector())->id(),
            "Stable ID must be 'log'.",
        );
    }

    /**
     * Creates a started collector wired to a debug module on top of a mocked web application.
     *
     * @return LogCollector Started collector.
     */
    private function makeCollector(): LogCollector
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        $module->logTarget = new LogTarget($module);

        $collector = new LogCollector();

        $collector->module = $module;

        $collector->startup();

        return $collector;
    }
}
