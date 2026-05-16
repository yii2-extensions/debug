<?php

declare(strict_types=1);

namespace yii\debug\tests\log;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\base\{Exception as YiiException, InvalidConfigException};
use yii\debug\{FlattenException, LogTarget, Module, Panel};
use yii\debug\panels\{DbPanel, MailPanel};
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

/**
 * Unit tests for {@see LogTarget} request-summary capture and panel hand-off, including closure
 * serialization in `LogPanel`.
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

        $data = $this->invoke(
            $logTarget,
            'collectSummary',
        );

        self::assertIsArray(
            $data,
            "'collectSummary' must hand back a structured array.",
        );
        self::assertArrayHasKey(
            'REQUEST_TIME_FLOAT',
            $_SERVER,
            'Web app bootstrap must seed REQUEST_TIME_FLOAT.',
        );
        self::assertArrayHasKey(
            'time',
            $data,
            'Summary must declare a captured request time.',
        );
        self::assertSame(
            $_SERVER['REQUEST_TIME_FLOAT'],
            $data['time'],
            'Captured time must mirror REQUEST_TIME_FLOAT exactly.',
        );
    }

    public function testCollectSummaryReadsSqlCountFromDbPanel(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = $this->newModuleWithIsolatedDataPath();

        $dbPanel = new class extends DbPanel {
            public function getProfileLogs(): array
            {
                return [
                    ['SELECT 1', Logger::LEVEL_PROFILE_BEGIN],
                    ['SELECT 1', Logger::LEVEL_PROFILE_END],
                    ['SELECT 2', Logger::LEVEL_PROFILE_BEGIN],
                    ['SELECT 2', Logger::LEVEL_PROFILE_END],
                ];
            }
        };

        $module->panels = ['db' => $dbPanel];

        $logTarget = new LogTarget($module);

        $summary = $this->invoke(
            $logTarget,
            'collectSummary',
        );

        self::assertIsArray(
            $summary,
            'Summary must surface as a structured array.',
        );
        self::assertSame(
            2,
            $summary['sqlCount'] ?? -1,
            "'sqlCount' must equal `count(profileLogs) / 2` when the DB panel is wired.",
        );

        $this->cleanupDataPath($module);
    }

    public function testExportAppliesConfiguredFileModeOnDataFiles(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = $this->newModuleWithIsolatedDataPath();

        $module->fileMode = 0o600;

        $logTarget = new LogTarget($module);

        $logTarget->export();

        $perms = fileperms("{$module->dataPath}/{$logTarget->tag}.data");

        self::assertIsInt(
            $perms,
            'Exported data file must exist on disk.',
        );
        self::assertSame(
            0o600,
            $perms & 0o777,
            "Configured 'fileMode' must be applied to the persisted data file.",
        );

        $this->cleanupDataPath($module);
    }

    public function testExportCapturesPanelExceptionsAsFlattenException(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = $this->newModuleWithIsolatedDataPath();

        $module->panels = [
            'broken' => new class extends Panel {
                public function getName(): string
                {
                    return 'broken';
                }

                public function save(): mixed
                {
                    throw new YiiException('panel save failure');
                }
            },
        ];

        $logTarget = new LogTarget($module);

        $logTarget->export();

        $manifest = $logTarget->loadManifest();

        $tag = array_key_first($manifest);

        self::assertIsString(
            $tag,
            'Manifest must hold the exported request.',
        );

        $logTarget->loadTagToPanels($tag);

        self::assertInstanceOf(
            FlattenException::class,
            $module->panels['broken']->getError(),
            "Panel exceptions thrown by 'save()' must surface as 'FlattenException' on load.",
        );

        $this->cleanupDataPath($module);
    }

    public function testGcEvictsExcessManifestEntriesAndDeletesDataFiles(): void
    {
        $module = $this->newModuleWithIsolatedDataPath();
        $module->historySize = 2;

        $logTarget = new LogTarget($module);

        $manifest = [];

        // historySize=2 + tolerance=10 → gc runs once count > 12; build 15 entries to force eviction.
        for ($i = 0; $i < 15; ++$i) {
            $tag = "tag-{$i}";
            $manifest[$tag] = ['tag' => $tag];

            file_put_contents("{$module->dataPath}/{$tag}.data", 'fixture');
        }

        $this->invoke(
            $logTarget,
            'gc',
            [&$manifest],
        );

        self::assertCount(
            2,
            $manifest,
            "'gc()' must trim the manifest down to 'historySize'.",
        );
        self::assertFileDoesNotExist(
            "{$module->dataPath}/tag-0.data",
            'Oldest data file must be removed alongside the manifest entry.',
        );

        $this->cleanupDataPath($module);
    }

    public function testGcEvictsMailFilesForExpiredRequests(): void
    {
        $module = $this->newModuleWithIsolatedDataPath();

        $module->historySize = 1;
        $mailPath = "{$module->dataPath}/mail";

        @mkdir($mailPath, 0o777, true);

        $mailPanel = new MailPanel();

        $mailPanel->mailPath = $mailPath;

        $module->panels = ['mail' => $mailPanel];

        $logTarget = new LogTarget($module);

        $manifest = [];

        for ($i = 0; $i < 15; ++$i) {
            $tag = "tag-{$i}";
            $mailFile = "msg-{$i}.eml";
            $manifest[$tag] = ['tag' => $tag, 'mailFiles' => [$mailFile]];

            file_put_contents("{$module->dataPath}/{$tag}.data", 'fixture');
            file_put_contents("{$mailPath}/{$mailFile}", 'eml');
        }

        $this->invoke(
            $logTarget,
            'gc',
            [&$manifest],
        );

        self::assertFileDoesNotExist(
            "{$mailPath}/msg-0.eml",
            "'gc()' must purge mail files referenced by evicted manifest entries.",
        );

        $this->cleanupDataPath($module);
    }

    public function testGcRemovesStaleDataFilesNotPresentInManifest(): void
    {
        $module = $this->newModuleWithIsolatedDataPath();
        $module->historySize = 1;

        $logTarget = new LogTarget($module);

        // Real entries the manifest will retain after eviction.
        $manifest = [];

        for ($i = 0; $i < 15; ++$i) {
            $tag = "live-{$i}";
            $manifest[$tag] = ['tag' => $tag];
            file_put_contents("{$module->dataPath}/{$tag}.data", 'fixture');
        }

        // Orphan data file with no manifest entry — must be wiped by `removeStaleDataFiles()`.
        $orphanFile = "{$module->dataPath}/orphan.data";

        file_put_contents($orphanFile, 'orphan-fixture');

        $this->invoke($logTarget, 'gc', [&$manifest]);

        self::assertFileDoesNotExist(
            $orphanFile,
            "Orphan '<tag>.data' files must be deleted by 'removeStaleDataFiles()'.",
        );

        $this->cleanupDataPath($module);
    }

    public function testLoadManifestReturnsEmptyArrayWhenFileIsCorrupted(): void
    {
        $module = $this->newModuleWithIsolatedDataPath();

        file_put_contents("{$module->dataPath}/index.data", 'this is not serialized data');

        self::assertSame(
            [],
            (new LogTarget($module))->loadManifest(),
            'Corrupt manifest content must yield an empty manifest array.',
        );

        $this->cleanupDataPath($module);
    }

    public function testLoadTagToPanelsDropsPanelsAbsentFromPayload(): void
    {
        $module = $this->newModuleWithIsolatedDataPath();

        $module->panels = [
            'orphan' => new class extends Panel {
                public function getName(): string
                {
                    return 'orphan';
                }
            },
        ];

        $logTarget = new LogTarget($module);

        file_put_contents(
            "{$module->dataPath}/{$logTarget->tag}.data",
            serialize(['summary' => [], 'exceptions' => []]),
        );

        $logTarget->loadTagToPanels($logTarget->tag);

        self::assertArrayNotHasKey(
            'orphan',
            $module->panels,
            'Panels missing from the payload and without exceptions must be evicted.',
        );

        $this->cleanupDataPath($module);
    }

    public function testLoadTagToPanelsToleratesCorruptedDataFile(): void
    {
        $module = $this->newModuleWithIsolatedDataPath();

        $logTarget = new LogTarget($module);

        file_put_contents("{$module->dataPath}/{$logTarget->tag}.data", 'corrupted');

        self::assertSame(
            [],
            $logTarget->loadTagToPanels($logTarget->tag),
            'Corrupt data file must collapse to an empty normalized payload.',
        );

        $this->cleanupDataPath($module);
    }

    public function testLogPanelSerializesClosureArgumentsToReadableSource(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = new Module('debug');

        $module->bootstrap(Yii::$app);

        $logTarget = $module->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            'Bootstrap must coerce logTarget to a LogTarget instance.',
        );

        Yii::$app->log->getLogger()->messages = [];

        Yii::debug('qwe');
        Yii::warning('asd');
        Yii::info(
            [
                'test_callback' => static function (string $cbArg): string {
                    return $cbArg . 'cbResult';
                },
            ],
        );

        Yii::$app->log->getLogger()->flush(true);

        $manifest = $logTarget->loadManifest();
        $lastEntry = reset($manifest);

        self::assertNotFalse(
            $lastEntry,
            'Flushing logs must yield at least one manifest entry.',
        );
        self::assertArrayHasKey(
            'tag',
            $lastEntry,
            'Manifest entry must expose its tag for panel hand-off.',
        );

        $tag = $lastEntry['tag'];

        self::assertIsString(
            $tag,
            'Manifest entry tag must be a string handle.',
        );

        $logTarget->loadTagToPanels($tag);

        self::assertArrayHasKey(
            'log',
            $module->panels,
            'Log panel must register after bootstrap.',
        );

        $messages = $this->extractLogMessages($module->panels['log']->data);

        $get = static function (int $position) use ($messages): array {
            self::assertArrayHasKey(
                $position,
                $messages,
                "Captured message list must include row {$position}.",
            );

            return $messages[$position];
        };

        $first = $get(0);
        $second = $get(1);
        $third = $get(2);

        self::assertSame(
            'qwe',
            $first[0],
            'First message body must be preserved.',
        );
        self::assertSame(
            Logger::LEVEL_TRACE,
            $first[1],
            'First message must keep its TRACE severity.',
        );
        self::assertSame(
            'asd',
            $second[0],
            'Second message body must be preserved.',
        );
        self::assertSame(
            Logger::LEVEL_WARNING,
            $second[1],
            'Second message must keep its WARNING severity.',
        );

        $closureMessage = $third[0];

        self::assertIsString(
            $closureMessage,
            'Serialized closure entry must surface as a string.',
        );
        self::assertStringContainsString(
            'test_callback',
            $closureMessage,
            'Array key must surface in the serialized output.',
        );
        self::assertStringContainsString(
            'function (string $cbArg)',
            $closureMessage,
            'Closure source must be retained verbatim.',
        );
        self::assertStringContainsString(
            "\$cbArg . 'cbResult'",
            $closureMessage,
            'Closure body literals must be preserved.',
        );
        self::assertSame(
            Logger::LEVEL_INFO,
            $third[1],
            'Closure-bearing entry must keep INFO severity.',
        );
    }

    public function testThrowInvalidConfigExceptionWhenIndexFileCannotBeOpened(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = $this->newModuleWithIsolatedDataPath();

        // Make 'index.data' a directory so 'touch()' and 'fopen(..., r+)' both fail in `updateIndexFile`.
        mkdir("{$module->dataPath}/index.data");

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'Unable to open debug data index file',
        );

        try {
            (new LogTarget($module))->export();
        } finally {
            @rmdir("{$module->dataPath}/index.data");

            $this->cleanupDataPath($module);
        }
    }

    public function testUpdateIndexFileToleratesNonArraySerializedManifest(): void
    {
        Yii::$app->getRequest()->setUrl('dummy');

        $module = $this->newModuleWithIsolatedDataPath();

        // Seed `index.data` with a serialized scalar so `narrowManifestEntries()` hits its non-array branch.
        file_put_contents("{$module->dataPath}/index.data", serialize('not-an-array'));

        $logTarget = new LogTarget($module);
        $logTarget->export();

        $manifest = $logTarget->loadManifest();

        self::assertArrayHasKey(
            $logTarget->tag,
            $manifest,
            'Export must succeed even when the previous manifest was a non-array scalar.',
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

        if (is_dir($dataPath)) {
            $files = glob("{$dataPath}/*");

            foreach (is_array($files) ? $files : [] as $file) {
                @unlink($file);
            }

            @rmdir($dataPath);
        }
    }

    /**
     * Pulls the messages list out of the log-panel payload, asserting structural invariants along the way.
     *
     * @return array<int, array{0: mixed, 1: int}>
     */
    private function extractLogMessages(mixed $panelData): array
    {
        self::assertIsArray(
            $panelData,
            'Log panel data must be a structured array.',
        );
        self::assertArrayHasKey(
            'messages',
            $panelData,
            "Log panel data must expose a 'messages' collection.",
        );

        $messages = $panelData['messages'];

        self::assertIsArray(
            $messages,
            "Log panel 'messages' must be a list.",
        );

        $rows = [];

        foreach ($messages as $index => $entry) {
            self::assertIsInt(
                $index,
                'Log message list must be numerically indexed.',
            );
            self::assertIsArray(
                $entry,
                "Each log message must be a '[body, severity, ...]' tuple.",
            );
            self::assertArrayHasKey(
                0,
                $entry,
                'Log message tuple must declare a body slot.',
            );
            self::assertArrayHasKey(
                1,
                $entry,
                'Log message tuple must declare a severity slot.',
            );

            $severity = $entry[1];

            self::assertIsInt(
                $severity,
                'Log message severity slot must be a level constant integer.',
            );

            $rows[$index] = [$entry[0], $severity];
        }

        return $rows;
    }

    private function newModuleWithIsolatedDataPath(): Module
    {
        $module = new Module('debug');

        $module->dataPath = sys_get_temp_dir() . '/debug-logtarget-' . uniqid();

        @mkdir($module->dataPath, 0o777, true);

        return $module;
    }
}
