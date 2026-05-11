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

        // Bootstrap or coverage runners may emit ambient warnings into the logger before this test sends its own
        // messages. Drop them so the assertions below address `messages[0..2]` against `qwe`/`asd`/closure only.
        Yii::$app->log->getLogger()->messages = [];

        Yii::debug('qwe');
        Yii::warning('asd');
        Yii::info(['test_callback' => function ($cbArg) {
            return $cbArg . 'cbResult';
        }]);

        Yii::$app->log->getLogger()->flush(true);

        $manifest = $logTarget->loadManifest();
        $lastEntry = reset($manifest);

        self::assertNotEmpty($lastEntry, 'Flushing logs must yield at least one manifest entry.');

        $logTarget->loadTagToPanels($lastEntry['tag']);
        $panelData = $module->panels['log']->data;

        self::assertArrayHasKey('messages', $panelData, 'Log panel data must expose a `messages` collection.');

        self::assertSame('qwe', $panelData['messages'][0][0], 'First message body must be preserved.');
        self::assertSame(Logger::LEVEL_TRACE, $panelData['messages'][0][1], 'First message must keep its TRACE severity.');

        self::assertSame('asd', $panelData['messages'][1][0], 'Second message body must be preserved.');
        self::assertSame(Logger::LEVEL_WARNING, $panelData['messages'][1][1], 'Second message must keep its WARNING severity.');

        $closureMessage = $panelData['messages'][2][0];

        self::assertStringContainsString('test_callback', $closureMessage, 'Array key must surface in the serialized output.');
        self::assertStringContainsString('function ($cbArg)', $closureMessage, 'Closure source must be retained verbatim.');
        self::assertStringContainsString("\$cbArg . 'cbResult'", $closureMessage, 'Closure body literals must be preserved.');
        self::assertSame(Logger::LEVEL_INFO, $panelData['messages'][2][1], 'Closure-bearing entry must keep INFO severity.');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockWebApplication();
    }
}
