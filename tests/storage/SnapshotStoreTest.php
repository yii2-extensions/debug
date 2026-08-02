<?php

declare(strict_types=1);

namespace yii\debug\tests\storage;

use PHPUnit\Framework\Attributes\Group;
use Xepozz\InternalMocker\MockerState;
use yii\base\InvalidConfigException;
use yii\debug\storage\{DebugSnapshot, RequestSummary, SnapshotStore};
use yii\debug\tests\support\TestCase;
use yii\helpers\FileHelper;

/**
 * Unit tests for {@see SnapshotStore} covering the JSON filesystem boundary: atomic writes, manifest locking, history
 * garbage collection, and the failure paths that keep a broken filesystem from corrupting a capture.
 *
 * @since 0.2
 */
#[Group('storage')]
final class SnapshotStoreTest extends TestCase
{
    private string $path;

    public function testInvalidJsonIsRejectedWithoutExecutingPayloads(): void
    {
        FileHelper::createDirectory($this->path);
        file_put_contents("{$this->path}/invalid.json", '{invalid');

        self::assertNull($this->store()->readSnapshot('invalid'));
    }

    public function testLoadManifestReturnsNothingWhenTheLockFileCannotBeOpened(): void
    {
        FileHelper::createDirectory($this->path);
        chmod($this->path, 0o555);

        try {
            self::assertSame(
                [],
                $this->store()->loadManifest(),
                'An unwritable directory must yield an empty manifest instead of throwing.',
            );
        } finally {
            chmod($this->path, 0o777);
        }
    }

    public function testReadSnapshotReturnsNullForATagThatWasNeverWritten(): void
    {
        FileHelper::createDirectory($this->path);

        self::assertNull(
            $this->store()->readSnapshot('never-written'),
            'A missing snapshot file must read back as `null`.',
        );
    }

    public function testRemovesOrphanSnapshotsMissingFromTheManifest(): void
    {
        $store = $this->store();

        $store->writeSnapshot('kept', new DebugSnapshot($this->summary('kept', 1_700_000_000.0), [], []));

        file_put_contents("{$this->path}/orphan.json", '{}');

        for ($index = 0; $index < 13; $index++) {
            $store->updateManifest($this->summary("tag-{$index}", 1_700_000_000.0 + $index), 2);
        }

        self::assertFileDoesNotExist(
            "{$this->path}/orphan.json",
            'A snapshot with no manifest entry must be swept.',
        );
    }

    public function testSnapshotAndManifestRoundTripThroughJson(): void
    {
        $store = $this->store();
        $older = $this->summary('older', 1_700_000_000.0);
        $newer = $this->summary('newer', 1_700_000_001.0);

        $store->writeSnapshot('newer', new DebugSnapshot($newer, ['panel' => ['value' => 1]], []));
        $store->updateManifest($older, 10);
        $store->updateManifest($newer, 10);

        $snapshot = $store->readSnapshot('newer');

        self::assertNotNull($snapshot);
        self::assertSame('newer', $snapshot->summary->tag);
        self::assertSame(['value' => 1], $snapshot->panels['panel'] ?? null);
        self::assertSame(['newer', 'older'], array_keys($store->loadManifest()));
    }

    public function testTagsCannotEscapeTheStorageDirectory(): void
    {
        self::assertNull($this->store()->readSnapshot('../outside'));

        $summary = $this->summary('../outside', 1_700_000_000.0);

        $this->expectException(InvalidConfigException::class);

        $this->store()->writeSnapshot('../outside', new DebugSnapshot($summary, [], []));
    }

    public function testThrowInvalidConfigExceptionWhenTheSnapshotCannotBeMovedIntoPlace(): void
    {
        FileHelper::createDirectory($this->path);
        chmod($this->path, 0o555);

        try {
            $this->expectException(InvalidConfigException::class);
            $this->expectExceptionMessage('Unable to replace debug data file');

            $this->store()->writeSnapshot(
                'blocked',
                new DebugSnapshot($this->summary('blocked', 1_700_000_000.0), [], []),
            );
        } finally {
            chmod($this->path, 0o777);
        }
    }

    public function testThrowInvalidConfigExceptionWhenTheTemporaryFileCannotBeCreated(): void
    {
        MockerState::addCondition('yii\\debug\\storage', 'tempnam', [], false, true);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Unable to write temporary debug data file');

        $this->store()->writeSnapshot(
            'blocked',
            new DebugSnapshot($this->summary('blocked', 1_700_000_000.0), [], []),
        );
    }

    public function testThrowInvalidConfigExceptionWhenTheTemporaryFileCannotBeWritten(): void
    {
        MockerState::addCondition('yii\\debug\\storage', 'file_put_contents', [], false, true);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Unable to write temporary debug data file');

        $this->store()->writeSnapshot(
            'blocked',
            new DebugSnapshot($this->summary('blocked', 1_700_000_000.0), [], []),
        );
    }

    public function testWritingJsonSnapshotRemovesLegacySerializedFiles(): void
    {
        FileHelper::createDirectory($this->path);
        file_put_contents("{$this->path}/legacy.data", 'serialized payload');

        $summary = $this->summary('current', 1_700_000_000.0);

        $this->store()->writeSnapshot('current', new DebugSnapshot($summary, [], []));

        self::assertFileDoesNotExist("{$this->path}/legacy.data");
        self::assertFileExists("{$this->path}/current.json");
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . '/yii-debug-storage-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        FileHelper::removeDirectory($this->path);

        parent::tearDown();
    }

    private function store(): SnapshotStore
    {
        return new SnapshotStore($this->path, 0o777, null);
    }

    private function summary(string $tag, float $time): RequestSummary
    {
        return new RequestSummary(
            tag: $tag,
            url: 'https://example.test/',
            ajax: false,
            method: 'GET',
            ip: '127.0.0.1',
            time: $time,
            statusCode: 200,
            sqlCount: 0,
            excessiveCallersCount: 0,
            mailCount: 0,
            mailFiles: [],
            processingTime: null,
            peakMemory: null,
        );
    }
}
