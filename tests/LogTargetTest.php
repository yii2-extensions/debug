<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\LogTarget;
use yii\debug\Module;
use yii\log\Logger;

/**
 * Unit tests for {@see LogTarget} request-summary capture and panel hand-off, including closure
 * serialization in `LogPanel`.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 2.1.29
 */
#[Group('log-target')]
final class LogTargetTest extends TestCase
{
    public function testCollectSummaryCapturesRequestTime(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = new Module('debug');
        $module->bootstrap(Yii::$app);

        $logTarget = new LogTarget($module);
        $data = $this->invoke($logTarget, 'collectSummary');

        self::assertIsArray($data, 'collectSummary must hand back a structured array.');
        self::assertArrayHasKey('REQUEST_TIME_FLOAT', $_SERVER, 'Web app bootstrap must seed REQUEST_TIME_FLOAT.');
        self::assertArrayHasKey('time', $data, 'Summary must declare a captured request time.');
        self::assertSame(
            $_SERVER['REQUEST_TIME_FLOAT'],
            $data['time'],
            'Captured time must mirror REQUEST_TIME_FLOAT exactly.',
        );
    }

    public function testLogPanelSerializesClosureArgumentsToReadableSource(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = new Module('debug');
        $module->bootstrap(Yii::$app);
        $logTarget = $module->logTarget;

        self::assertInstanceOf(LogTarget::class, $logTarget, 'bootstrap() must coerce logTarget to a LogTarget instance.');

        // Bootstrap or coverage runners may emit ambient warnings into the logger before this test sends its own
        // messages. Drop them so the assertions below address `messages[0..2]` against `qwe`/`asd`/closure only.
        Yii::$app->log->getLogger()->messages = [];

        Yii::debug('qwe');
        Yii::warning('asd');
        Yii::info(['test_callback' => function (string $cbArg): string {
            return $cbArg . 'cbResult';
        }]);

        Yii::$app->log->getLogger()->flush(true);

        $manifest = $logTarget->loadManifest();
        $lastEntry = reset($manifest);

        self::assertNotFalse($lastEntry, 'Flushing logs must yield at least one manifest entry.');
        self::assertArrayHasKey('tag', $lastEntry, 'Manifest entry must expose its tag for panel hand-off.');

        $tag = $lastEntry['tag'];

        self::assertIsString($tag, 'Manifest entry tag must be a string handle.');

        $logTarget->loadTagToPanels($tag);

        self::assertArrayHasKey('log', $module->panels, 'Log panel must register after bootstrap.');

        $messages = $this->extractLogMessages($module->panels['log']->data);

        $get = static function (int $position) use ($messages): array {
            self::assertArrayHasKey($position, $messages, "Captured message list must include row {$position}.");

            return $messages[$position];
        };

        $first = $get(0);
        $second = $get(1);
        $third = $get(2);

        self::assertSame('qwe', $first[0], 'First message body must be preserved.');
        self::assertSame(Logger::LEVEL_TRACE, $first[1], 'First message must keep its TRACE severity.');

        self::assertSame('asd', $second[0], 'Second message body must be preserved.');
        self::assertSame(Logger::LEVEL_WARNING, $second[1], 'Second message must keep its WARNING severity.');

        $closureMessage = $third[0];

        self::assertIsString($closureMessage, 'Serialized closure entry must surface as a string.');
        self::assertStringContainsString('test_callback', $closureMessage, 'Array key must surface in the serialized output.');
        self::assertStringContainsString('function (string $cbArg)', $closureMessage, 'Closure source must be retained verbatim.');
        self::assertStringContainsString("\$cbArg . 'cbResult'", $closureMessage, 'Closure body literals must be preserved.');
        self::assertSame(Logger::LEVEL_INFO, $third[1], 'Closure-bearing entry must keep INFO severity.');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockWebApplication();
    }

    /**
     * Pulls the messages list out of the log-panel payload, asserting structural invariants along the way.
     *
     * @return array<int, array{0: mixed, 1: int}>
     */
    private function extractLogMessages(mixed $panelData): array
    {
        self::assertIsArray($panelData, 'Log panel data must be a structured array.');
        self::assertArrayHasKey('messages', $panelData, 'Log panel data must expose a `messages` collection.');

        $messages = $panelData['messages'];

        self::assertIsArray($messages, 'Log panel `messages` must be a list.');

        $rows = [];

        foreach ($messages as $index => $entry) {
            self::assertIsInt($index, 'Log message list must be numerically indexed.');
            self::assertIsArray($entry, 'Each log message must be a `[body, severity, ...]` tuple.');
            self::assertArrayHasKey(0, $entry, 'Log message tuple must declare a body slot.');
            self::assertArrayHasKey(1, $entry, 'Log message tuple must declare a severity slot.');

            $severity = $entry[1];

            self::assertIsInt($severity, 'Log message severity slot must be a level constant integer.');

            $rows[$index] = [$entry[0], $severity];
        }

        return $rows;
    }
}
