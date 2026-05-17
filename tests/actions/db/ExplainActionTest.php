<?php

declare(strict_types=1);

namespace yii\debug\tests\actions\db;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\base\Controller as BaseController;
use yii\db\Connection;
use yii\debug\actions\db\ExplainAction;
use yii\debug\controllers\DefaultController;
use yii\debug\{LogTarget, Module};
use yii\debug\panels\DbPanel;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;
use yii\web\AssetManager;
use yii\web\HttpException;

/**
 * Unit tests for {@see ExplainAction} covering the panel-missing / non-DefaultController / missing-seq error paths,
 * plus the happy path that renders the SQLite `EXPLAIN QUERY PLAN` view for a captured query.
 */
#[Group('actions')]
#[Group('db')]
final class ExplainActionTest extends TestCase
{
    public function testRunRendersAjaxPartialWhenRequestIsAjax(): void
    {
        $module = $this->bootDebugModuleWithSqlite();

        $dbPanel = $module->panels['db'] ?? null;

        self::assertInstanceOf(
            DbPanel::class,
            $dbPanel,
            'DB panel must be wired in the bootstrap.',
        );

        $messages = [
            ['SELECT 1', Logger::LEVEL_PROFILE_BEGIN, 'yii\\db\\Command::query', 1_700_000_000.0, [], 1024],
            ['SELECT 1', Logger::LEVEL_PROFILE_END, 'yii\\db\\Command::query', 1_700_000_000.05, [], 2048],
        ];

        $this->writeSnapshot($module, 'tag-ajax', ['db' => ['messages' => $messages]]);

        $controller = new DefaultController('default', $module);
        $action = new ExplainAction('db-explain', $controller, ['panel' => $dbPanel]);

        Yii::$app->getRequest()->setUrl('dummy');

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        try {
            $html = $action->run('0', 'tag-ajax');
        } finally {
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        }

        self::assertStringContainsString(
            'SELECT 1',
            $html,
            'AJAX hits must render the partial view (no layout); query must still surface.',
        );
    }

    public function testRunRendersExplainQueryPlanForSqliteFixture(): void
    {
        $module = $this->bootDebugModuleWithSqlite();

        $dbPanel = $module->panels['db'] ?? null;

        self::assertInstanceOf(
            DbPanel::class,
            $dbPanel,
            'DB panel must be wired in the bootstrap.',
        );

        $messages = [
            ['SELECT 1', Logger::LEVEL_PROFILE_BEGIN, 'yii\\db\\Command::query', 1_700_000_000.0, [], 1024],
            ['SELECT 1', Logger::LEVEL_PROFILE_END, 'yii\\db\\Command::query', 1_700_000_000.05, [], 2048],
        ];

        $this->writeSnapshot($module, 'tag-explain', ['db' => ['messages' => $messages]]);

        $controller = new DefaultController('default', $module);
        $action = new ExplainAction('db-explain', $controller, ['panel' => $dbPanel]);

        Yii::$app->getRequest()->setUrl('dummy');
        Yii::$app->getRequest()->setBodyParams([]);

        $html = $action->run('0', 'tag-explain');

        self::assertStringContainsString(
            'SELECT 1',
            $html,
            'Rendered view must surface the explained query verbatim.',
        );
    }

    public function testThrowHttpExceptionForMissingTimingSeq(): void
    {
        $module = $this->bootDebugModuleWithSqlite();

        $dbPanel = $module->panels['db'] ?? null;

        self::assertInstanceOf(
            DbPanel::class,
            $dbPanel,
            'DB panel must be wired in the bootstrap.',
        );

        $this->writeSnapshot($module, 'tag-empty', ['db' => ['messages' => []]]);

        $controller = new DefaultController('default', $module);
        $action = new ExplainAction('db-explain', $controller, ['panel' => $dbPanel]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage(
            'Log message not found.',
        );

        $action->run('99', 'tag-empty');
    }

    public function testThrowHttpExceptionWhenControllerIsNotDefaultController(): void
    {
        $this->mockWebApplication();

        $controller = new BaseController('test', new Module('debug'));
        $action = new ExplainAction('db-explain', $controller, ['panel' => new DbPanel()]);

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
        $action = new ExplainAction('db-explain', $controller);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage(
            'DbPanel instance is not set',
        );

        $action->run('0', 'irrelevant');
    }

    private function bootDebugModuleWithSqlite(): Module
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'db' => ['class' => Connection::class, 'dsn' => 'sqlite::memory:'],
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
