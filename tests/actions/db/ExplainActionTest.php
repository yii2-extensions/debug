<?php

declare(strict_types=1);

namespace yii\debug\tests\actions\db;

use PHPForge\Debug\Panel\Db\{DbSnapshot, QueryRow};
use PHPForge\Debug\Storage\PanelSnapshot;
use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\base\Controller as BaseController;
use yii\db\Connection;
use yii\debug\actions\db\ExplainAction;
use yii\debug\controllers\DefaultController;
use yii\debug\Module;
use yii\debug\panels\DbPanel;
use yii\debug\tests\support\TestCase;
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
    public function testDbExplainViewRendersEmptyStateWhenResultsAreEmpty(): void
    {
        $module = $this->bootDebugModuleWithSqlite();

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

        $html = $controller->renderPartial(
            'db-explain',
            [
                'query' => 'SELECT 1',
                'results' => [],
            ],
        );

        self::assertStringContainsString(
            'EXPLAIN returned no rows.',
            $html,
            'Empty explain results must render the empty-state hint.',
        );
    }

    public function testDbExplainViewRendersEmptyStringCellWithoutNullPlaceholder(): void
    {
        $module = $this->bootDebugModuleWithSqlite();

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

        $html = $controller->renderPartial(
            'db-explain',
            [
                'query' => 'SELECT 1',
                'results' => [['detail' => null, 'extra' => '']],
            ],
        );

        self::assertSame(
            1,
            substr_count($html, '<em>NULL</em>'),
            "Only the 'null' cell may render the 'NULL' placeholder; '' must stay an empty cell.",
        );
    }

    public function testRunRendersAjaxPartialWhenRequestIsAjax(): void
    {
        $module = $this->bootDebugModuleWithSqlite();

        $dbPanel = $module->panels['db'] ?? null;

        self::assertInstanceOf(
            DbPanel::class,
            $dbPanel,
            'DB panel must be wired in the bootstrap.',
        );

        $this->writeSnapshot(
            $module,
            'tag-ajax',
            ['db' => new DbSnapshot([self::queryRow('SELECT 1')])],
        );

        $controller = new DefaultController('default', $module);
        $action = new ExplainAction('db-explain', $controller, ['panel' => $dbPanel]);

        Yii::$app->controller = $controller;

        Yii::$app->getRequest()->setUrl('dummy');

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        try {
            $html = $action->run('0', 'tag-ajax');
        } finally {
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        }

        self::assertStringContainsString(
            '<span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>',
            $html,
            'AJAX hits must render the partial view (no layout); query must still surface highlighted.',
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

        $this->writeSnapshot(
            $module,
            'tag-explain',
            ['db' => new DbSnapshot([self::queryRow('SELECT 1')])],
        );

        $controller = new DefaultController('default', $module);
        $action = new ExplainAction('db-explain', $controller, ['panel' => $dbPanel]);

        Yii::$app->controller = $controller;

        Yii::$app->getRequest()->setUrl('dummy');
        Yii::$app->getRequest()->setBodyParams([]);

        $html = $action->run('0', 'tag-explain');

        self::assertStringContainsString(
            '<span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>',
            $html,
            'Rendered view must surface the explained query highlighted.',
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

        $this->writeSnapshot(
            $module,
            'tag-empty',
            ['db' => new DbSnapshot([])],
        );

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

    private static function queryRow(string $query): QueryRow
    {
        return new QueryRow(
            type: 'SELECT',
            query: $query,
            duration: 50.0,
            trace: [],
            traceHash: 'hash',
            timestamp: 1_700_000_000_000.0,
            seq: 0,
            duplicate: 1,
            rows: null,
        );
    }

    /**
     * @param array<string, PanelSnapshot> $panels
     */
    private function writeSnapshot(Module $module, string $tag, array $panels): void
    {
        $this->writeDebugSnapshot($module, $tag, $panels);
    }
}
