<?php

declare(strict_types=1);

namespace yii\debug\tests\log;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\base\{Exception as YiiException, InvalidConfigException};
use yii\debug\{LogTarget, Module, Panel};
use yii\debug\panels\config\ConfigSnapshot;
use yii\debug\panels\{ConfigPanel, DbPanel, LogPanel, MailPanel};
use yii\debug\storage\{DebugSnapshot, ExceptionSnapshot, PanelSnapshot, RequestSummary};
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

/**
 * Tests the typed JSON capture, manifest, failure-isolation, and panel-hydration boundaries.
 */
#[Group('log-target')]
final class LogTargetTest extends TestCase
{
    public function testCollectSummaryCapturesRequestTime(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = new Module('debug');
        $module->bootstrap(Yii::$app);
        $summary = $this->invoke(new LogTarget($module), 'collectSummary');

        self::assertInstanceOf(RequestSummary::class, $summary);
        self::assertArrayHasKey('REQUEST_TIME_FLOAT', $_SERVER);
        self::assertSame($_SERVER['REQUEST_TIME_FLOAT'], $summary->time);
    }

    public function testCollectSummaryReadsSqlCountFromDbPanel(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = $this->newModuleWithIsolatedDataPath();
        $module->panels = [
            'db' => new class extends DbPanel {
                public function getProfileLogs(): array
                {
                    return [
                        ['SELECT 1', Logger::LEVEL_PROFILE_BEGIN],
                        ['SELECT 1', Logger::LEVEL_PROFILE_END],
                        ['SELECT 2', Logger::LEVEL_PROFILE_BEGIN],
                        ['SELECT 2', Logger::LEVEL_PROFILE_END],
                    ];
                }
            },
        ];

        $summary = $this->invoke(new LogTarget($module), 'collectSummary');

        self::assertInstanceOf(RequestSummary::class, $summary);
        self::assertSame(2, $summary->sqlCount);

        $this->cleanupDataPath($module);
    }

    public function testEvictedMailCleanupIsSkippedWithoutAMailPanel(): void
    {
        $module = $this->newModuleWithIsolatedDataPath();

        unset($module->panels['mail']);

        $logTarget = new LogTarget($module);
        $evicted = $this->requestSummary('tag-evicted', ['mailCount' => 1, 'mailFiles' => ['gone.eml']]);

        $this->invoke($logTarget, 'removeMailFiles', [$evicted]);

        self::assertNotContains('mail', array_keys($module->panels), 'The fixture must register no mail panel.');
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

        self::assertIsInt($permissions);
        self::assertSame(0o600, $permissions & 0o777);

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

        self::assertInstanceOf(RequestSummary::class, $summary);
        self::assertInstanceOf(ExceptionSnapshot::class, $module->panels['broken']->getError());
        self::assertSame('panel capture failure', $module->panels['broken']->getError()->getMessage());

        $this->cleanupDataPath($module);
    }

    public function testExportResetsCorruptManifest(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = $this->newModuleWithIsolatedDataPath();

        file_put_contents("{$module->dataPath}/index.json", 'null');

        $logTarget = new LogTarget($module);
        $logTarget->export();

        self::assertArrayHasKey($logTarget->tag, $logTarget->loadManifest());

        $this->cleanupDataPath($module);
    }

    public function testExportThrowsWhenLockFileCannotBeOpened(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = $this->newModuleWithIsolatedDataPath();

        mkdir("{$module->dataPath}/index.lock");

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Unable to open debug data lock file');

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

        self::assertIsString($contents);

        $snapshot = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($snapshot, 'The snapshot file must decode to an object.');
        self::assertSame(
            DebugSnapshot::VERSION,
            $snapshot['version'] ?? null,
            'The envelope must carry the current storage version.',
        );
        self::assertArrayHasKey('panels', $snapshot, 'The envelope must carry the panel payloads.');
        self::assertArrayHasKey('summary', $snapshot, 'The envelope must carry the manifest summary.');
        self::assertArrayHasKey('failures', $snapshot, 'The envelope must carry the isolated panel failures.');

        $this->cleanupDataPath($module);
    }

    public function testLoadManifestReturnsEmptyArrayForCorruptJson(): void
    {
        $module = $this->newModuleWithIsolatedDataPath();

        file_put_contents("{$module->dataPath}/index.json", 'not-json');

        self::assertSame([], (new LogTarget($module))->loadManifest());

        $this->cleanupDataPath($module);
    }

    public function testLoadTagDropsPanelsAbsentFromSnapshot(): void
    {
        $module = $this->newModuleWithIsolatedDataPath();
        $module->panels = ['orphan' => new Panel()];

        $this->writeDebugSnapshot($module, 'tag-empty', []);
        (new LogTarget($module))->loadTagToPanels('tag-empty');

        self::assertArrayNotHasKey('orphan', $module->panels);

        $this->cleanupDataPath($module);
    }

    public function testLoadTagIsolatesInvalidPanelPayload(): void
    {
        $module = $this->newModuleWithIsolatedDataPath();
        $configPanel = new ConfigPanel();
        $configPanel->id = 'config';
        $module->panels = ['config' => $configPanel];

        $this->writeDebugSnapshot($module, 'invalid-panel', ['config' => ConfigSnapshot::capture([])]);

        $file = "{$module->dataPath}/invalid-panel.json";
        $snapshot = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($snapshot);
        $panels = $snapshot['panels'] ?? null;

        self::assertIsArray($panels);

        $panels['config'] = ['unexpected' => true];
        $snapshot['panels'] = $panels;

        file_put_contents($file, json_encode($snapshot, JSON_THROW_ON_ERROR));

        $summary = (new LogTarget($module))->loadTagToPanels('invalid-panel');

        self::assertInstanceOf(RequestSummary::class, $summary);
        self::assertInstanceOf(ExceptionSnapshot::class, $configPanel->getError());

        $this->cleanupDataPath($module);
    }

    public function testLoadTagRejectsCorruptJson(): void
    {
        $module = $this->newModuleWithIsolatedDataPath();

        file_put_contents("{$module->dataPath}/corrupt.json", 'corrupt');

        self::assertNull((new LogTarget($module))->loadTagToPanels('corrupt'));

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

        self::assertInstanceOf(LogTarget::class, $logTarget);

        Yii::$app->log->getLogger()->messages = [];
        Yii::debug('qwe');
        Yii::warning('asd');
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

        self::assertInstanceOf(RequestSummary::class, $summary);

        $logTarget->loadTagToPanels($summary->tag);

        $logPanel = $module->panels['log'] ?? null;

        self::assertInstanceOf(LogPanel::class, $logPanel);

        $rows = $logPanel->getMessages();

        self::assertCount(3, $rows, 'Every flushed message must survive the JSON round-trip.');
        self::assertSame('qwe', $rows[0]->message, 'First message must round-trip verbatim.');
        self::assertSame(Logger::LEVEL_TRACE, $rows[0]->level, 'First message keeps its trace level.');
        self::assertSame('asd', $rows[1]->message, 'Second message must round-trip verbatim.');
        self::assertSame(Logger::LEVEL_WARNING, $rows[1]->level, 'Second message keeps its warning level.');
        self::assertStringContainsString('test_callback', $rows[2]->message, 'Closure key must stay readable.');
        self::assertStringContainsString(
            'function (string $argument)',
            $rows[2]->message,
            'Closure signature must stay readable.',
        );
        self::assertSame(Logger::LEVEL_INFO, $rows[2]->level, 'Third message keeps its info level.');
    }

    public function testManifestGarbageCollectionDeletesExpiredJsonSnapshots(): void
    {
        $module = $this->newModuleWithIsolatedDataPath();
        $module->historySize = 2;

        for ($index = 0; $index < 13; ++$index) {
            $this->writeDebugSnapshot($module, "tag-{$index}", []);
        }

        $manifest = (new LogTarget($module))->loadManifest();

        self::assertCount(2, $manifest);
        self::assertFileDoesNotExist("{$module->dataPath}/tag-0.json");
        self::assertFileExists("{$module->dataPath}/tag-12.json");

        $this->cleanupDataPath($module);
    }

    public function testManifestGarbageCollectionDeletesExpiredMailFiles(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = $this->newModuleWithIsolatedDataPath();
        $module->historySize = 1;
        $mailPath = "{$module->dataPath}/mail";
        $mailPanel = new MailPanel();
        $mailPanel->mailPath = $mailPath;
        $module->panels = ['mail' => $mailPanel];

        @mkdir($mailPath, 0o777, true);

        $logTarget = new LogTarget($module);

        for ($index = 0; $index < 15; ++$index) {
            $file = "message-{$index}.eml";

            file_put_contents("{$mailPath}/{$file}", 'message');
            $this->setInaccessibleProperty($mailPanel, 'messages', [['file' => $file]]);
            $logTarget->tag = "mail-tag-{$index}";
            $logTarget->export();
        }

        self::assertFileDoesNotExist("{$mailPath}/message-0.eml");
        self::assertFileExists("{$mailPath}/message-14.eml");

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
