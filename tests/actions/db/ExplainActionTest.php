<?php

declare(strict_types=1);

namespace yii\debug\tests\actions\db;

use PHPForge\Debug\Panel\Db\{DbMessage, DbSnapshot, QueryRow};
use PHPForge\Debug\Storage\PanelSnapshot;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use Yii;
use yii\db\Connection;
use yii\debug\actions\db\ExplainAction;
use yii\debug\exception\Message;
use yii\debug\Module;
use yii\debug\panels\DbPanel;
use yii\debug\tests\provider\ExplainActionProvider;
use yii\debug\tests\support\TestCase;
use yii\web\{AssetManager, ServerErrorHttpException};

/**
 * Unit tests for {@see ExplainAction} covering the missing-panel-service path, the `400`/`404` empty-body lookup
 * contract, the unexplainable-statement diagnostic, and the happy paths that render the SQLite `EXPLAIN QUERY PLAN`
 * view for a captured query.
 *
 * {@see ExplainActionProvider} for test case data providers.
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
                'error' => null,
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
                'error' => null,
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

    /**
     * @param mixed $seq Sequence number sent by the request.
     * @param mixed $tag Request tag sent by the request.
     * @param int $expected HTTP status code the lookup must answer with.
     */
    #[DataProviderExternal(ExplainActionProvider::class, 'rejectedLookups')]
    public function testRunAnswersEmptyBodyForRejectedLookups(mixed $seq, mixed $tag, int $expected): void
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
            ExplainActionProvider::KNOWN_TAG,
            ['db' => new DbSnapshot([self::queryRow('SELECT 7', seq: ExplainActionProvider::KNOWN_SEQ)])],
        );

        $action = new ExplainAction('db-explain');

        $action->setModule($module);

        self::assertSame(
            '',
            $action->run($seq, $tag, $dbPanel),
            'Body must stay empty.',
        );
        self::assertSame(
            $expected,
            Yii::$app->getResponse()->getStatusCode(),
            'Status must classify the rejection.',
        );
    }

    public function testRunAnswersNotFoundForAnUnknownTagEvenWhenTheSequenceIsHydrated(): void
    {
        $module = $this->bootDebugModuleWithSqlite();

        $dbPanel = $module->panels['db'] ?? null;

        self::assertInstanceOf(
            DbPanel::class,
            $dbPanel,
            'DB panel must be wired in the bootstrap.',
        );

        $dbPanel->hydrate(
            (new DbSnapshot([self::queryRow('SELECT 7', seq: ExplainActionProvider::KNOWN_SEQ)]))->jsonSerialize(),
        );

        $action = new ExplainAction('db-explain');

        $action->setModule($module);

        self::assertSame(
            '',
            $action->run((string) ExplainActionProvider::KNOWN_SEQ, 'tag-missing', $dbPanel),
            'Body must stay empty.',
        );
        self::assertSame(
            404,
            Yii::$app->getResponse()->getStatusCode(),
            'Rows left over from an earlier snapshot must not serve an unknown tag.',
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

    public function testRunRendersDatabaseExceptionInFullDebuggerShell(): void
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
            'tag-full-error',
            ['db' => new DbSnapshot([self::queryRow('SELECT * FROM "<stale&table>"')])],
        );

        $action = new ExplainAction('db-explain');

        $action->setModule($module);

        Yii::$app->getRequest()->setUrl('dummy');
        Yii::$app->getRequest()->setBodyParams([]);

        $html = $action->run('0', 'tag-full-error', $dbPanel);

        self::assertSame(
            200,
            Yii::$app->getResponse()->getStatusCode(),
            'A handled EXPLAIN rejection must render as a diagnostic page rather than an HTTP error response.',
        );
        self::assertStringStartsWith(
            '<!DOCTYPE html>',
            $html,
            'Regular EXPLAIN diagnostics must preserve the full debugger layout.',
        );
        self::assertStringContainsString(
            <<<HTML
            <p class="yii-debug-explain-empty">
            EXPLAIN failed: SQLSTATE[HY000]: General error: 1 no such table: &lt;stale&amp;table&gt;
            HTML,
            $html,
            'The full-page diagnostic must surface the escaped database rejection.',
        );
        self::assertStringNotContainsString(
            'Stack trace:',
            $html,
            'The handled full-page diagnostic must not leak the framework exception stack.',
        );
    }

    public function testRunRendersEscapedDatabaseExceptionAsAjaxDiagnostic(): void
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
            'tag-ajax-error',
            ['db' => new DbSnapshot([self::queryRow('SELECT * FROM "<script>alert(1)</script>"')])],
        );

        $action = new ExplainAction('db-explain');

        $action->setModule($module);

        Yii::$app->getRequest()->setUrl('dummy');

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        try {
            $html = $action->run('0', 'tag-ajax-error', $dbPanel);
        } finally {
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        }

        self::assertSame(
            200,
            Yii::$app->getResponse()->getStatusCode(),
            'A handled EXPLAIN rejection must remain a successful diagnostic response for the inline AJAX workflow.',
        );
        self::assertStringContainsString(
            <<<HTML
            <p class="yii-debug-explain-empty">
            EXPLAIN failed: SQLSTATE[HY000]: General error: 1 no such table: &lt;script&gt;alert(1)&lt;/script&gt;
            HTML,
            $html,
            'The AJAX diagnostic must explain the database rejection and escape identifier-derived markup.',
        );
        self::assertStringNotContainsString(
            '<script>',
            $html,
            'Database exception text must not inject executable markup.',
        );
        self::assertStringNotContainsString(
            'Stack trace:',
            $html,
            'The handled diagnostic must not leak the framework exception stack.',
        );
        self::assertStringNotContainsString(
            '<!DOCTYPE html>',
            $html,
            'AJAX diagnostics must omit the debugger layout.',
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

    public function testRunReportsExplainUnavailableForUnexplainableStatements(): void
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
            'tag-unexplainable',
            [
                'db' => new DbSnapshot(
                    [
                        self::queryRow('DROP TABLE x', 'DROP'),
                        self::queryRow('SELECT 1; SELECT 2', seq: 1),
                    ],
                ),
            ],
        );

        $action = new ExplainAction('db-explain');

        $action->setModule($module);

        Yii::$app->getRequest()->setUrl('dummy');
        Yii::$app->getRequest()->setBodyParams([]);

        foreach (['0', '1'] as $seq) {
            $html = $action->run($seq, 'tag-unexplainable', $dbPanel);

            self::assertSame(
                200,
                Yii::$app->getResponse()->getStatusCode(),
                'Unexplainable statements stay a successful diagnostic.',
            );
            self::assertStringContainsString(
                DbMessage::EXPLAIN_UNAVAILABLE->value,
                $html,
                'The shared unavailable message must replace the plan.',
            );
            self::assertStringNotContainsString(
                'SQLSTATE',
                $html,
                'No EXPLAIN command may reach the driver.',
            );
        }
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

    public function testRunUsesPlainExplainPrefixForNonSqliteDrivers(): void
    {
        $module = $this->bootDebugModuleWithSqlite();

        Yii::$app->set(
            'db',
            new class (['dsn' => 'sqlite::memory:']) extends Connection {
                public function getDriverName(): string
                {
                    return 'mysql';
                }

                protected function initConnection(): void
                {
                    // Skip the MySQL charset bootstrap so the faked driver name can run over the SQLite backend.
                    $this->trigger(self::EVENT_AFTER_OPEN);
                }
            },
        );

        $dbPanel = $module->panels['db'] ?? null;

        self::assertInstanceOf(
            DbPanel::class,
            $dbPanel,
            'DB panel must be wired in the bootstrap.',
        );

        $this->writeSnapshot(
            $module,
            'tag-prefix',
            ['db' => new DbSnapshot([self::queryRow('SELECT 1')])],
        );

        $action = new ExplainAction('db-explain');

        $action->setModule($module);

        Yii::$app->getRequest()->setUrl('dummy');
        Yii::$app->getRequest()->setBodyParams([]);

        $html = $action->run('0', 'tag-prefix', $dbPanel);

        self::assertStringContainsString(
            'opcode',
            $html,
            'Plain EXPLAIN must reach the driver and list virtual-machine opcodes.',
        );
    }

    public function testThrowServerErrorHttpExceptionWhenDbPanelIsDisabled(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');
        $action = new ExplainAction('db-explain');

        $action->setModule($module);

        $this->expectException(ServerErrorHttpException::class);
        $this->expectExceptionMessage(
            Message::REQUIRED_SERVICE_NOT_FOUND->getMessage('panel'),
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

    private static function queryRow(string $query, string $type = 'SELECT', int $seq = 0): QueryRow
    {
        return QueryRow::create($query, 50.0, 1_700_000_000_000.0)
            ->withType($type)
            ->withTraceHash('hash')
            ->withSequence($seq);
    }

    /**
     * @param array<string, PanelSnapshot> $panels
     */
    private function writeSnapshot(Module $module, string $tag, array $panels): void
    {
        $this->writeDebugSnapshot($module, $tag, $panels);
    }
}
