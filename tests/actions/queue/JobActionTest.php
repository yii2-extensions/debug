<?php

declare(strict_types=1);

namespace yii\debug\tests\actions\queue;

use PHPForge\Debug\Panel\Queue\QueueSnapshot;
use PHPForge\Debug\Storage\PanelSnapshot;
use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\actions\queue\JobAction;
use yii\debug\exception\Message;
use yii\debug\Module;
use yii\debug\panels\QueuePanel;
use yii\debug\tests\support\TestCase;
use yii\web\{AssetManager, HttpException, ServerErrorHttpException};

/**
 * Unit tests for {@see JobAction} covering the missing-panel-service and record-not-found error paths, and the happy
 * path that renders the queue-job detail view for a captured record (both regular and AJAX requests).
 */
#[Group('actions')]
#[Group('queue')]
final class JobActionTest extends TestCase
{
    public function testRunRejectsMissingQueueRecord(): void
    {
        $module = $this->bootDebugModule();

        $queuePanel = $module->panels['queue'] ?? null;

        self::assertInstanceOf(
            QueuePanel::class,
            $queuePanel,
            'Queue panel must be wired in the bootstrap.',
        );

        $this->writeSnapshot(
            $module,
            'tag-empty-queue',
            ['queue' => QueueSnapshot::capture([])],
        );

        $action = new JobAction('queue-job');

        $action->setModule($module);

        try {
            $action->run('0', 'tag-empty-queue', $queuePanel);

            self::fail(
                'A missing queue record must throw.',
            );
        } catch (HttpException $exception) {
            self::assertSame(
                404,
                $exception->statusCode,
                'A missing queue record must throw a 404.',
            );
            self::assertSame(
                Message::QUEUE_JOB_RECORD_NOT_FOUND->getMessage(),
                $exception->getMessage(),
                'A missing queue record must report the adapter error.',
            );
        }
    }

    public function testRunRendersAjaxPartialWhenRequestIsAjax(): void
    {
        $module = $this->bootDebugModule();

        $queuePanel = $module->panels['queue'] ?? null;

        self::assertInstanceOf(
            QueuePanel::class,
            $queuePanel,
            'Queue panel must be wired in the bootstrap.',
        );

        $records = [
            [
                'eventType' => 'push',
                'componentId' => 'queue',
                'driverName' => 'Sync',
                'driverClass' => 'yii\\queue\\sync\\Queue',
                'isAsync' => false,
                'jobClass' => 'app\\jobs\\HelloJob',
                'jobId' => 'job-1',
                'time' => 1_700_000_000.0,
                'fields' => [],
            ],
        ];

        $this->writeSnapshot(
            $module,
            'tag-queue-ajax',
            ['queue' => QueueSnapshot::capture($records)],
        );

        $action = new JobAction('queue-job');

        $action->setModule($module);

        Yii::$app->getRequest()->setUrl('dummy');

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        try {
            $html = $action->run('0', 'tag-queue-ajax', $queuePanel);
        } finally {
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        }

        self::assertStringContainsString(
            'HelloJob',
            $html,
            'AJAX hits must render the partial view; job class must still surface.',
        );
        self::assertStringNotContainsString(
            '<!DOCTYPE html>',
            $html,
            'AJAX rendering must omit the debugger layout.',
        );
    }

    public function testRunRendersQueueJobDetailViewForCapturedRecord(): void
    {
        $module = $this->bootDebugModule();

        $queuePanel = $module->panels['queue'] ?? null;

        self::assertInstanceOf(
            QueuePanel::class,
            $queuePanel,
            'Queue panel must be wired in the bootstrap.',
        );

        $records = [
            [
                'eventType' => 'push',
                'componentId' => 'queue',
                'driverName' => 'Sync',
                'driverClass' => 'yii\\queue\\sync\\Queue',
                'isAsync' => false,
                'jobClass' => 'app\\jobs\\HelloJob',
                'jobId' => 'job-1',
                'time' => 1_700_000_000.0,
                'fields' => ['name' => 'Wilmer'],
            ],
        ];

        $this->writeSnapshot(
            $module,
            'tag-queue',
            ['queue' => QueueSnapshot::capture($records)],
        );

        $action = new JobAction('queue-job');

        $action->setModule($module);

        Yii::$app->getRequest()->setUrl('dummy');

        $html = $action->run('0', 'tag-queue', $queuePanel);

        self::assertStringContainsString(
            'HelloJob',
            $html,
            'Rendered view must surface the job class short name.',
        );
        self::assertStringStartsWith(
            '<!DOCTYPE html>',
            $html,
            'Regular rendering must include the debugger layout.',
        );
    }

    public function testThrowHttpExceptionForMissingRecordSeq(): void
    {
        $module = $this->bootDebugModule();

        $queuePanel = $module->panels['queue'] ?? null;

        self::assertInstanceOf(
            QueuePanel::class,
            $queuePanel,
            'Queue panel must be wired in the bootstrap.',
        );

        $this->writeSnapshot(
            $module,
            'tag-empty-queue',
            ['queue' => QueueSnapshot::capture([])],
        );

        $action = new JobAction('queue-job');

        $action->setModule($module);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage(
            Message::QUEUE_JOB_RECORD_NOT_FOUND->getMessage(),
        );

        $action->run('99', 'tag-empty-queue', $queuePanel);
    }

    public function testThrowServerErrorHttpExceptionWhenActionHasNoModule(): void
    {
        $this->mockWebApplication();

        $action = new JobAction('queue-job');

        $this->expectException(ServerErrorHttpException::class);
        $this->expectExceptionMessage(
            'Could not load required service: panel',
        );

        $action->runWithParams(['seq' => '0', 'tag' => 'irrelevant']);
    }

    private function bootDebugModule(): Module
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'assetManager' => [
                        'class' => AssetManager::class,
                        'basePath' => dirname(__DIR__, 3) . '/runtime/assets',
                        'baseUrl' => '/assets',
                    ],
                ],
            ],
        );

        @mkdir(Yii::getAlias('@runtime/assets'), 0o777, true);

        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        Yii::$app->setModule('debug', $module);

        $module->bootstrap(Yii::$app);

        return $module;
    }

    /**
     * @param array<string, PanelSnapshot> $panels
     */
    private function writeSnapshot(Module $module, string $tag, array $panels): void
    {
        $this->writeDebugSnapshot($module, $tag, $panels);
    }
}
