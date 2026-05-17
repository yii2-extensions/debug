<?php

declare(strict_types=1);

namespace yii\debug\tests\actions\queue;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\base\Controller as BaseController;
use yii\debug\actions\queue\JobAction;
use yii\debug\controllers\DefaultController;
use yii\debug\{LogTarget, Module};
use yii\debug\panels\QueuePanel;
use yii\debug\tests\support\TestCase;
use yii\web\{AssetManager, HttpException};

/**
 * Unit tests for {@see JobAction} covering the panel-missing / non-DefaultController / record-not-found error paths,
 * and the happy path that renders the queue-job detail view for a captured record (both regular and AJAX requests).
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 *
 * @since 0.1
 */
#[Group('actions')]
#[Group('queue')]
final class JobActionTest extends TestCase
{
    public function testRunCollapsesRecordsToEmptyWhenPanelDataLacksRecordsKey(): void
    {
        $module = $this->bootDebugModule();

        $queuePanel = $module->panels['queue'] ?? null;

        self::assertInstanceOf(
            QueuePanel::class,
            $queuePanel,
            'Queue panel must be wired in the bootstrap.',
        );

        // Snapshot payload without the expected 'records' key — exercises the `: []` fallback branch.
        $this->writeSnapshot($module, 'tag-malformed-queue', ['queue' => ['something-else' => 'value']]);

        $controller = new DefaultController('default', $module);
        $action = new JobAction('queue-job', $controller, ['panel' => $queuePanel]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage(
            'Queue job record not found.',
        );

        $action->run('0', 'tag-malformed-queue');
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

        $this->writeSnapshot($module, 'tag-queue-ajax', ['queue' => ['records' => $records]]);

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

        $action = new JobAction('queue-job', $controller, ['panel' => $queuePanel]);

        Yii::$app->getRequest()->setUrl('dummy');

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        try {
            $html = $action->run('0', 'tag-queue-ajax');
        } finally {
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        }

        self::assertStringContainsString(
            'HelloJob',
            $html,
            'AJAX hits must render the partial view; job class must still surface.',
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

        $this->writeSnapshot($module, 'tag-queue', ['queue' => ['records' => $records]]);

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

        $action = new JobAction('queue-job', $controller, ['panel' => $queuePanel]);

        Yii::$app->getRequest()->setUrl('dummy');

        $html = $action->run('0', 'tag-queue');

        self::assertStringContainsString(
            'HelloJob',
            $html,
            'Rendered view must surface the job class short name.',
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

        $this->writeSnapshot($module, 'tag-empty-queue', ['queue' => ['records' => []]]);

        $controller = new DefaultController('default', $module);
        $action = new JobAction('queue-job', $controller, ['panel' => $queuePanel]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage(
            'Queue job record not found.',
        );

        $action->run('99', 'tag-empty-queue');
    }

    public function testThrowHttpExceptionWhenControllerIsNotDefaultController(): void
    {
        $this->mockWebApplication();

        $controller = new BaseController('test', new Module('debug'));
        $action = new JobAction('queue-job', $controller, ['panel' => new QueuePanel()]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage(
            'must run inside the debug DefaultController',
        );

        $action->run('0', 'irrelevant');
    }

    public function testThrowHttpExceptionWhenPanelIsMissing(): void
    {
        $this->mockWebApplication();

        $controller = new BaseController('test', new Module('debug'));
        $action = new JobAction('queue-job', $controller);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage(
            'QueuePanel instance is not set',
        );

        $action->run('0', 'irrelevant');
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
     * @param array<string, array<string, mixed>> $panelData Per-panel data shapes, keyed by panel id.
     */
    private function writeSnapshot(Module $module, string $tag, array $panelData): void
    {
        $logTarget = $module->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            'logTarget must be wired by bootstrap.',
        );

        $logTarget->tag = $tag;

        $dataPath = Yii::getAlias($module->dataPath);

        @mkdir($dataPath, 0o777, true);

        $payload = [];

        foreach ($panelData as $id => $data) {
            $payload[$id] = serialize($data);
        }

        $payload['summary'] = [
            'tag' => $tag,
            'url' => 'dummy',
            'method' => 'GET',
            'time' => 1_700_000_000.0,
            'ip' => '127.0.0.1',
            'statusCode' => 200,
        ];
        $payload['exceptions'] = [];

        file_put_contents("{$dataPath}/{$tag}.data", serialize($payload));
        file_put_contents("{$dataPath}/index.data", serialize([$tag => $payload['summary']]));
    }
}
