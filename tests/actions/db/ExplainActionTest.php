<?php

declare(strict_types=1);

namespace yii\debug\tests\actions\db;

use PHPForge\Debug\Panel\Db\{DbSnapshot, QueryRow};
use PHPForge\Debug\Storage\PanelSnapshot;
use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\db\Connection;
use yii\debug\actions\db\ExplainAction;
use yii\debug\Module;
use yii\debug\panels\DbPanel;
use yii\debug\tests\support\TestCase;
use yii\web\{AssetManager, HttpException, ServerErrorHttpException};

/**
 * Unit tests for {@see ExplainAction} covering the missing-panel-service and missing-seq error paths, plus the happy
 * paths that render the SQLite `EXPLAIN QUERY PLAN` view for a captured query.
 */
#[Group('actions')]
#[Group('db')]
final class ExplainActionTest extends TestCase
{
    public function testDbExplainViewRendersEmptyStateWhenResultsAreEmpty(): void
    {
        $module = $this->bootDebugModuleWithSqlite();

        $action = new ExplainAction('db-explain');

        $action->setModule($module);

        $html = $action->renderPartial(
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

        $action = new ExplainAction('db-explain');

        $action->setModule($module);

        $html = $action->renderPartial(
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

        $action = new ExplainAction('db-explain');

        $action->setModule($module);

        Yii::$app->getRequest()->setUrl('dummy');

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        try {
            $html = $action->run('0', 'tag-ajax', $dbPanel);
        } finally {
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        }

        self::assertStringContainsString(
            <<<HTML
            <span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>
            HTML,
            $html,
            'AJAX hits must render the partial view (no layout); query must still surface highlighted.',
        );
        self::assertStringNotContainsString(
            '<!DOCTYPE html>',
            $html,
            'AJAX rendering must omit the debugger layout.',
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

        $action = new ExplainAction('db-explain');

        $action->setModule($module);

        Yii::$app->getRequest()->setUrl('dummy');
        Yii::$app->getRequest()->setBodyParams([]);

        $html = $action->run('0', 'tag-explain', $dbPanel);

        self::assertStringContainsString(
            <<<HTML
            <span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>
            HTML,
            $html,
            'Rendered view must surface the explained query highlighted.',
        );
        self::assertStringStartsWith(
            '<!DOCTYPE html>',
            $html,
            'Regular rendering must include the debugger layout.',
        );
    }

    public function testRunResolvesPanelFromModuleServiceLocatorOnDispatch(): void
    {
        $module = $this->bootDebugModuleWithSqlite();

        $this->writeSnapshot(
            $module,
            'tag-di',
            ['db' => new DbSnapshot([self::queryRow('SELECT 1')])],
        );

        $action = new ExplainAction('db-explain');

        $action->setModule($module);

        Yii::$app->getRequest()->setUrl('dummy');
        Yii::$app->getRequest()->setBodyParams([]);

        $html = $action->runWithParams(['seq' => '0', 'tag' => 'tag-di']);

        self::assertIsString(
            $html,
            'Dispatch must produce rendered HTML.',
        );
        self::assertStringContainsString(
            <<<HTML
            <span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>
            HTML,
            $html,
            'Injected panel must serve the captured query.',
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

        $action = new ExplainAction('db-explain');

        $action->setModule($module);

        try {
            $action->run('99', 'tag-empty', $dbPanel);

            self::fail(
                'A missing timing sequence must throw.',
            );
        } catch (HttpException $exception) {
            self::assertSame(
                404,
                $exception->statusCode,
                "A missing timing sequence must throw a '404'.",
            );
            self::assertSame(
                'Log message not found.',
                $exception->getMessage(),
                'A missing timing sequence must throw a "Log message not found." message.',
            );
        }
    }

    public function testThrowServerErrorHttpExceptionWhenDbPanelIsDisabled(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        $action = new ExplainAction('db-explain');

        $action->setModule($module);

        $this->expectException(ServerErrorHttpException::class);
        $this->expectExceptionMessage(
            'Could not load required service: panel',
        );

        $action->runWithParams(['seq' => '0', 'tag' => 'irrelevant']);
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
