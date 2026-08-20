<?php

declare(strict_types=1);

namespace yii\debug\tests\log;

use PHPForge\Debug\Panel\Config\ConfigSnapshot;
use PHPForge\Debug\Storage\{DebugSnapshot, ExceptionSnapshot, PanelSnapshot, RequestSummary};
use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\base\{Exception as YiiException, InvalidConfigException};
use yii\debug\collectors\MailCollector;
use yii\debug\{LogTarget, Module, Panel};
use yii\debug\panels\{ConfigPanel, LogPanel};
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_array;
use function is_dir;
use function reset;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Tests the typed JSON capture, manifest, failure-isolation, and panel-hydration boundaries.
 */
#[Group('log-target')]
final class LogTargetTest extends TestCase
{
    public function testBeginRequestIsolatesTagAndMessageStateAcrossWorkerCycles(): void
    {
        $target = new LogTarget(new Module('debug'));
        $initialTag = $target->tag;
        $target->messages = [['previous request']];

        $target->beginRequest();
        $firstRequestTag = $target->tag;

        self::assertNotSame($initialTag, $firstRequestTag, 'A new request must receive a fresh tag.');
        self::assertSame([], $target->messages, 'A new request must not inherit the previous message buffer.');

        $target->messages = [['first request']];
        $target->beginRequest();
        $secondRequestTag = $target->tag;

        self::assertNotSame($firstRequestTag, $secondRequestTag, 'Every worker request must rotate the tag.');
        self::assertSame([], $target->messages, 'Every worker request must reset collected messages.');

        foreach ([$initialTag, $firstRequestTag, $secondRequestTag] as $tag) {
            self::assertMatchesRegularExpression(
                '/\A[0-9a-f]{32}\z/',
                $tag,
                'Every request tag must be a 128-bit lowercase hexadecimal identifier.',
            );
        }
    }

    public function testCollectAppendsMessagesAcrossBatches(): void
    {
        $target = new LogTarget(new Module('debug'));

        $target->collect([['first']], false);
        $target->collect([['second']], false);

        self::assertSame(
            [['first'], ['second']],
            $target->messages,
            'Collecting another batch must retain messages captured previously.',
        );
    }

    public function testCollectSummaryCapturesRequestTime(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = new Module('debug');

        $module->bootstrap(Yii::$app);

        $summary = $this->invoke(new LogTarget($module), 'collectSummary');

        self::assertInstanceOf(
            RequestSummary::class,
            $summary,
            'The summary must be a RequestSummary instance.',
        );
        self::assertArrayHasKey(
            'REQUEST_TIME_FLOAT',
            $_SERVER,
            'The summary must read the request time from the server superglobal.',
        );
        self::assertSame(
            $_SERVER['REQUEST_TIME_FLOAT'],
            $summary->time,
            'The summary time must match the server request time.',
        );
    }

    public function testCollectSummaryDefaultsDatabaseCountsToZeroWithoutProfileLogs(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = $this->newModuleWithIsolatedDataPath();

        $module->panels = [];

        $summary = $this->invoke(new LogTarget($module), 'collectSummary');

        self::assertInstanceOf(
            RequestSummary::class,
            $summary,
            'The summary must be a RequestSummary instance.',
        );
        self::assertSame(
            0,
            $summary->sqlCount,
            'Missing DB panel must report zero SQL queries.',
        );
        self::assertSame(
            0,
            $summary->excessiveCallersCount,
            'Missing DB panel must report zero excessive callers.',
        );

        $this->cleanupDataPath($module);
    }

    public function testCollectSummaryNormalizesIntegerRequestTimeToFloat(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $_SERVER['REQUEST_TIME_FLOAT'] = 123;

        $module = new Module('debug');

        $module->bootstrap(Yii::$app);

        $summary = $this->invoke(new LogTarget($module), 'collectSummary');

        self::assertInstanceOf(
            RequestSummary::class,
            $summary,
            'The summary must be a RequestSummary instance.',
        );
        self::assertSame(
            123.0,
            $summary->time,
            'Integer server timestamps must satisfy the float summary contract.',
        );
    }

    public function testCollectSummaryReadsSqlCountFromDbCollector(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = $this->newModuleWithIsolatedDataPath();

        $logTarget = new LogTarget($module);

        $logTarget->messages = [
            ['SELECT 1', Logger::LEVEL_PROFILE_BEGIN, 'yii\db\Command::query', 0.0, [], 0],
            ['SELECT 1', Logger::LEVEL_PROFILE_END, 'yii\db\Command::query', 0.001, [], 0],
            ['SELECT 2', Logger::LEVEL_PROFILE_BEGIN, 'yii\db\Command::query', 0.002, [], 0],
            ['SELECT 2', Logger::LEVEL_PROFILE_END, 'yii\db\Command::query', 0.003, [], 0],
        ];

        $module->getCollectorCoordinator()->startup();

        $summary = $this->invoke($logTarget, 'collectSummary');

        $module->getCollectorCoordinator()->shutdown();

        self::assertInstanceOf(
            RequestSummary::class,
            $summary,
            'The summary must be a RequestSummary instance.',
        );
        self::assertSame(
            2,
            $summary->sqlCount,
            'The DB panel must report the correct number of SQL queries.',
        );

        $this->cleanupDataPath($module);
    }

    public function testCollectSummaryRedactsSensitiveQueryValues(): void
    {
        Yii::$app->getRequest()->setUrl('/debug?token=query-secret&page=1');

        $module = new Module('debug');
        $module->bootstrap(Yii::$app);

        $summary = $this->invoke(new LogTarget($module), 'collectSummary');

        self::assertInstanceOf(RequestSummary::class, $summary, 'The summary must be captured.');
        self::assertStringNotContainsString('query-secret', $summary->url, 'Manifest URLs must not retain query secrets.');
        self::assertStringContainsString(
            'token=%5Bredacted%5D',
            $summary->url,
            'The redaction marker must remain visible.',
        );
    }

    public function testEvictedMailCleanupIsSkippedWithoutAMailCollector(): void
    {
        $module = new class ('debug') extends Module {
            protected function coreCollectors(): array
            {
                return [];
            }
        };

        $logTarget = new LogTarget($module);

        $evicted = $this->requestSummary('tag-evicted', ['mailCount' => 1, 'mailFiles' => ['gone.eml']]);

        $this->invoke($logTarget, 'removeMailFiles', [$evicted]);

        self::assertNull(
            $module->getCollectorCoordinator()->collector('mail'),
            'The fixture must register no mail collector.',
        );
    }

    public function testExportAppliesConfiguredFileModeToJsonSnapshot(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('POSIX permission bits are not portable to Windows.');
        }

        Yii::$app->getRequest()->setUrl('dummy');

        $module = $this->newModuleWithIsolatedDataPath();

        $module->fileMode = 0o600;

        $logTarget = new LogTarget($module);

        $logTarget->export();

        $permissions = fileperms("{$module->dataPath}/{$logTarget->tag}.json");

        self::assertIsInt(
            $permissions,
            'The file permissions must be an integer.',
        );
        self::assertSame(
            0o600,
            $permissions & 0o777,
            'The file must have the correct permissions.',
        );

        $this->cleanupDataPath($module);
    }

    public function testExportCapturesPanelFailureAsExceptionSnapshot(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = $this->newModuleWithIsolatedDataPath();

        $module->panels = [
            'broken' => new class extends Panel {
                public function capture(): PanelSnapshot|null
                {
                    throw new YiiException('panel capture failure');
                }
            },
        ];

        $logTarget = new LogTarget($module);

        $logTarget->export();

        $summary = $logTarget->loadTagToPanels($logTarget->tag);

        self::assertInstanceOf(
            RequestSummary::class,
            $summary,
            'The summary must be a RequestSummary instance.',
        );
        self::assertInstanceOf(
            ExceptionSnapshot::class,
            $module->panels['broken']->getError(),
            'The broken panel must carry an exception snapshot.',
        );
        self::assertSame(
            'panel capture failure',
            $module->panels['broken']->getError()->getMessage(),
            'The broken panel must carry the correct exception message.',
        );

        $this->cleanupDataPath($module);
    }

    public function testExportDoesNotPersistRequestSecretsToJson(): void
    {
        $request = Yii::$app->getRequest();
        $request->setUrl('/login?token=query-secret');
        $request->setRawBody('{"password":"body-secret"}');
        $request->setBodyParams(['password' => 'body-secret']);
        $request->getHeaders()->set('Authorization', 'Bearer header-secret');

        $module = $this->newModuleWithIsolatedDataPath();
        $logTarget = new LogTarget($module);

        $logTarget->export();

        $json = file_get_contents("{$module->dataPath}/{$logTarget->tag}.json");

        self::assertIsString($json, 'The persisted snapshot must be readable.');
        self::assertStringNotContainsString('query-secret', $json, 'Persisted URLs must not contain query secrets.');
        self::assertStringNotContainsString('body-secret', $json, 'Persisted bodies must not contain body secrets.');
        self::assertStringNotContainsString('header-secret', $json, 'Persisted headers must not contain credentials.');
        self::assertStringContainsString('[redacted]', $json, 'Persisted snapshots must retain an explicit marker.');

        $this->cleanupDataPath($module);
    }

    public function testExportRemovesCapturedMailWhenSnapshotPersistenceFails(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = $this->newModuleWithIsolatedDataPath();
        $mailCollector = $module->getCollectorCoordinator()->collector('mail');

        self::assertInstanceOf(MailCollector::class, $mailCollector, 'Mail collector must be registered.');

        $mailPath = "{$module->dataPath}/mail";
        $mailCollector->mailPath = $mailPath;
        mkdir($mailPath, recursive: true);
        file_put_contents("{$mailPath}/orphan.eml", 'message');
        mkdir("{$module->dataPath}/index.lock");

        $module->getCollectorCoordinator()->startup();
        $this->setInaccessibleProperty($mailCollector, 'messages', [['file' => 'orphan.eml']]);

        try {
            (new LogTarget($module))->export();

            self::fail('An invalid lock path must make snapshot persistence fail.');
        } catch (InvalidConfigException $exception) {
            self::assertStringContainsString(
                'Unable to open debug data lock file',
                $exception->getMessage(),
                'Mail cleanup must preserve the snapshot persistence failure.',
            );
        }

        self::assertFileDoesNotExist(
            "{$mailPath}/orphan.eml",
            'A failed snapshot commit must not leave its captured mail file behind.',
        );

        rmdir("{$module->dataPath}/index.lock");
        $this->cleanupDataPath($module);
    }

    public function testExportResetsCorruptManifest(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = $this->newModuleWithIsolatedDataPath();

        file_put_contents(
            "{$module->dataPath}/index.json",
            'null',
        );

        $logTarget = new LogTarget($module);
        $logTarget->export();

        self::assertArrayHasKey(
            $logTarget->tag,
            $logTarget->loadManifest(),
            'The manifest must contain the current log target tag.',
        );

        $this->cleanupDataPath($module);
    }

    public function testExportThrowsWhenLockFileCannotBeOpened(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = $this->newModuleWithIsolatedDataPath();

        mkdir("{$module->dataPath}/index.lock");

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'Unable to open debug data lock file',
        );

        try {
            (new LogTarget($module))->export();
        } finally {
            @rmdir("{$module->dataPath}/index.lock");

            $this->cleanupDataPath($module);
        }
    }

    public function testExportWritesTheVersionedJsonEnvelope(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = $this->newModuleWithIsolatedDataPath();

        $logTarget = new LogTarget($module);

        $logTarget->export();

        $contents = file_get_contents("{$module->dataPath}/{$logTarget->tag}.json");

        self::assertIsString(
            $contents,
            'The snapshot file must be readable as a string.',
        );

        $snapshot = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray(
            $snapshot,
            'The snapshot file must decode to an object.',
        );
        self::assertSame(
            DebugSnapshot::VERSION,
            $snapshot['version'] ?? null,
            'The envelope must carry the current storage version.',
        );
        self::assertArrayHasKey(
            'panels',
            $snapshot,
            'The envelope must carry the panel payloads.',
        );
        self::assertArrayHasKey(
            'summary',
            $snapshot,
            'The envelope must carry the manifest summary.',
        );
        self::assertArrayHasKey(
            'failures',
            $snapshot,
            'The envelope must carry the isolated panel failures.',
        );

        $this->cleanupDataPath($module);
    }

    public function testLoadManifestReturnsEmptyArrayForCorruptJson(): void
    {
        $module = $this->newModuleWithIsolatedDataPath();

        file_put_contents(
            "{$module->dataPath}/index.json",
            'not-json',
        );

        self::assertSame(
            [],
            (new LogTarget($module))->loadManifest(),
            'Corrupt manifest JSON must be treated as an empty manifest.',
        );

        $this->cleanupDataPath($module);
    }

    public function testLoadTagDropsPanelsAbsentFromSnapshot(): void
    {
        $module = $this->newModuleWithIsolatedDataPath();
        $module->panels = ['orphan' => new Panel()];

        $this->writeDebugSnapshot(
            $module,
            'tag-empty',
            [],
        );

        (new LogTarget($module))->loadTagToPanels('tag-empty');

        self::assertArrayNotHasKey(
            'orphan',
            $module->panels,
            'Panels absent from the snapshot must be dropped from the module.',
        );

        $this->cleanupDataPath($module);
    }

    public function testLoadTagIsolatesInvalidPanelPayload(): void
    {
        $module = $this->newModuleWithIsolatedDataPath();
        $configPanel = new ConfigPanel();
        $configPanel->id = 'config';
        $module->panels = ['config' => $configPanel];

        $this->writeDebugSnapshot(
            $module,
            'invalid-panel',
            ['config' => ConfigSnapshot::capture([])],
        );

        $file = "{$module->dataPath}/invalid-panel.json";

        $snapshot = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray(
            $snapshot,
            'The snapshot file must decode to an array.',
        );

        $panels = $snapshot['panels'] ?? null;

        self::assertIsArray(
            $panels,
            'The panels must decode to an array.',
        );

        $panels['config'] = ['unexpected' => true];
        $snapshot['panels'] = $panels;

        file_put_contents(
            $file,
            json_encode($snapshot, JSON_THROW_ON_ERROR),
        );

        $summary = (new LogTarget($module))->loadTagToPanels('invalid-panel');

        self::assertInstanceOf(
            RequestSummary::class,
            $summary,
            "The summary must be a 'RequestSummary' instance.",
        );
        self::assertInstanceOf(
            ExceptionSnapshot::class,
            $configPanel->getError(),
            "The error must be an 'ExceptionSnapshot' instance.",
        );

        $this->cleanupDataPath($module);
    }

    public function testLoadTagRejectsCorruptJson(): void
    {
        $module = $this->newModuleWithIsolatedDataPath();

        file_put_contents(
            "{$module->dataPath}/corrupt.json",
            'corrupt',
        );

        self::assertNull(
            (new LogTarget($module))->loadTagToPanels('corrupt'),
            "Corrupt snapshot JSON must be rejected and return 'null'.",
        );

        $this->cleanupDataPath($module);
    }

    public function testLoadTagRejectsIncompatibleStorageVersion(): void
    {
        $module = $this->newModuleWithIsolatedDataPath();

        file_put_contents(
            "{$module->dataPath}/old.json",
            '{"version":2,"summary":{},"panels":{},"failures":{}}',
        );

        self::assertNull((new LogTarget($module))->loadTagToPanels('old'));

        $this->cleanupDataPath($module);
    }

    public function testLogPanelCapturesClosureArgumentsAsReadableText(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = new Module('debug');

        $module->bootstrap(Yii::$app);

        $logTarget = $module->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            'The module must register a log target.',
        );

        Yii::$app->log->getLogger()->messages = [];

        Yii::debug(
            'qwe',
        );
        Yii::warning(
            'asd',
        );
        Yii::info(
            [
                'test_callback' => static function (string $argument): string {
                    return $argument . 'result';
                },
            ],
        );

        Yii::$app->log->getLogger()->flush(true);

        $manifest = $logTarget->loadManifest();

        $summary = reset($manifest);

        self::assertInstanceOf(
            RequestSummary::class,
            $summary,
            'The manifest must contain a request summary.',
        );

        $logTarget->loadTagToPanels($summary->tag);

        $logPanel = $module->panels['log'] ?? null;

        self::assertInstanceOf(
            LogPanel::class,
            $logPanel,
            'The log panel must be an instance of LogPanel.',
        );

        $rows = $logPanel->getMessages();

        self::assertCount(
            3,
            $rows,
            'Every flushed message must survive the JSON round-trip.',
        );
        self::assertSame(
            'qwe',
            $rows[0]->message,
            'First message must round-trip verbatim.',
        );
        self::assertSame(
            Logger::LEVEL_TRACE,
            $rows[0]->level,
            'First message keeps its trace level.',
        );
        self::assertSame(
            'asd',
            $rows[1]->message,
            'Second message must round-trip verbatim.',
        );
        self::assertSame(
            Logger::LEVEL_WARNING,
            $rows[1]->level,
            'Second message keeps its warning level.',
        );
        self::assertStringContainsString(
            'test_callback',
            $rows[2]->message,
            'Closure key must stay readable.',
        );
        self::assertStringContainsString(
            'function (string $argument)',
            $rows[2]->message,
            'Closure signature must stay readable.',
        );
        self::assertSame(
            Logger::LEVEL_INFO,
            $rows[2]->level,
            'Third message keeps its info level.',
        );
    }

    public function testManifestGarbageCollectionDeletesExpiredJsonSnapshots(): void
    {
        $module = $this->newModuleWithIsolatedDataPath();
        $module->historySize = 2;

        for ($index = 0; $index < 13; ++$index) {
            $this->writeDebugSnapshot(
                $module,
                "tag-{$index}",
                [],
            );
        }

        $manifest = (new LogTarget($module))->loadManifest();

        self::assertCount(
            2,
            $manifest,
            'The manifest must retain only the two most recent snapshots.',
        );
        self::assertFileDoesNotExist(
            "{$module->dataPath}/tag-0.json",
            'The oldest snapshot must be deleted.',
        );
        self::assertFileExists(
            "{$module->dataPath}/tag-12.json",
            'The most recent snapshot must be retained.',
        );

        $this->cleanupDataPath($module);
    }

    public function testManifestGarbageCollectionDeletesExpiredMailFiles(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = $this->newModuleWithIsolatedDataPath();

        $module->historySize = 1;
        $mailPath = "{$module->dataPath}/mail";

        $mailCollector = $module->getCollectorCoordinator()->collector('mail');

        self::assertInstanceOf(
            MailCollector::class,
            $mailCollector,
            'Mail collector must be registered by default.',
        );

        $mailCollector->mailPath = $mailPath;

        @mkdir($mailPath, 0o777, true);

        $logTarget = new LogTarget($module);

        for ($index = 0; $index < 15; ++$index) {
            $file = "message-{$index}.eml";

            file_put_contents(
                "{$mailPath}/{$file}",
                'message',
            );

            $module->getCollectorCoordinator()->startup();

            $this->setInaccessibleProperty($mailCollector, 'messages', [['file' => $file]]);

            $logTarget->tag = "mail-tag-{$index}";

            $logTarget->export();
        }

        self::assertFileDoesNotExist(
            "{$mailPath}/message-0.eml",
            'The oldest mail file must be deleted.',
        );
        self::assertFileExists(
            "{$mailPath}/message-14.eml",
            'The most recent mail file must be retained.',
        );

        $this->cleanupDataPath($module);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockWebApplication();
    }

    private function cleanupDataPath(Module $module): void
    {
        $dataPath = $module->dataPath;

        if (!is_dir($dataPath)) {
            return;
        }

        $files = glob("{$dataPath}/*");

        foreach (is_array($files) ? $files : [] as $file) {
            if (is_dir($file)) {
                $nested = glob("{$file}/*");

                foreach (is_array($nested) ? $nested : [] as $nestedFile) {
                    @unlink($nestedFile);
                }

                @rmdir($file);

                continue;
            }

            @unlink($file);
        }

        @rmdir($dataPath);
    }

    private function newModuleWithIsolatedDataPath(): Module
    {
        $module = new Module('debug');

        $module->dataPath = sys_get_temp_dir() . '/debug-logtarget-' . uniqid();

        @mkdir($module->dataPath, 0o777, true);

        return $module;
    }
}
