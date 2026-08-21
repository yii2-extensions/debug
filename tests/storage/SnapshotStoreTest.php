<?php

declare(strict_types=1);

namespace yii\debug\tests\storage;

use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary};
use PHPUnit\Framework\Attributes\Group;
use Xepozz\InternalMocker\MockerState;
use yii\base\InvalidConfigException;
use yii\debug\storage\SnapshotStore;
use yii\debug\tests\support\TestCase;
use yii\helpers\FileHelper;

/**
 * Unit tests for {@see SnapshotStore} covering the JSON filesystem boundary: atomic writes, manifest locking, history
 * garbage collection, and the failure paths that keep a broken filesystem from corrupting a capture.
 */
#[Group('storage')]
final class SnapshotStoreTest extends TestCase
{
    private string $path;

    public function testInvalidJsonIsRejectedWithoutExecutingPayloads(): void
    {
        FileHelper::createDirectory($this->path);

        file_put_contents("{$this->path}/invalid.json", '{invalid');

        self::assertNull(
            $this->store()->readSnapshot('invalid'),
            'Malformed JSON must read as `null`.',
        );
    }

    public function testLoadManifestReturnsNothingWhenTheLockFileCannotBeOpened(): void
    {
        $store = $this->store();

        $summary = $this->summary('tag-1', 1_700_000_000.0);

        $store->writeSnapshot(
            new DebugSnapshot($summary, [], []),
            10,
        );

        MockerState::addCondition(
            'PHPForge\\Debug\\Storage',
            'fopen',
            [],
            false,
            true,
        );

        self::assertSame(
            [],
            $store->loadManifest(),
            'An unopenable lock file must yield an empty manifest instead of throwing.',
        );
    }

    public function testReadSnapshotRejectsTagThatEscapesTheStorageDirectory(): void
    {
        self::assertNull(
            $this->store()->readSnapshot('../outside'),
            'Unsafe read tag must yield `null`.',
        );
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

        $kept = $this->summary('kept', 1_700_000_000.0);

        $store->writeSnapshot(
            new DebugSnapshot($kept, [], []),
            2,
        );

        file_put_contents("{$this->path}/orphan.json", '{}');

        for ($index = 0; $index < 13; $index++) {
            $summary = $this->summary("tag-{$index}", 1_700_000_000.0 + $index);

            $store->writeSnapshot(
                new DebugSnapshot($summary, [], []),
                2,
            );
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

        $store->writeSnapshot(
            new DebugSnapshot($older, [], []),
            10,
        );
        $store->writeSnapshot(
            new DebugSnapshot($newer, ['panel' => ['value' => 1]], []),
            10,
        );

        $snapshot = $store->readSnapshot('newer');

        self::assertNotNull(
            $snapshot,
            'Persisted snapshot must remain readable.',
        );
        self::assertSame(
            'newer',
            $snapshot->summary->tag,
            'Request tag must survive persistence.',
        );
        self::assertSame(
            ['value' => 1],
            $snapshot->panels['panel'] ?? null,
            'Panel payload must survive persistence.',
        );
        self::assertSame(
            ['newer', 'older'],
            array_keys($store->loadManifest()),
            'Manifest entries must be ordered newest first.',
        );
    }

    public function testThrowInvalidConfigExceptionForTagThatEscapesTheStorageDirectory(): void
    {
        $summary = $this->summary('../outside', 1_700_000_000.0);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'Invalid debug snapshot tag: ../outside',
        );

        $this->store()->writeSnapshot(new DebugSnapshot($summary, [], []), 10);
    }

    public function testThrowInvalidConfigExceptionWhenTheSnapshotCannotBeMovedIntoPlace(): void
    {
        MockerState::addCondition(
            'PHPForge\\Debug\\Storage',
            'rename',
            [],
            false,
            true,
        );

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'Unable to replace debug data file',
        );

        $summary = $this->summary('blocked', 1_700_000_000.0);

        $this->store()->writeSnapshot(new DebugSnapshot($summary, [], []), 10);
    }

    public function testThrowInvalidConfigExceptionWhenTheTemporaryFileCannotBeCreated(): void
    {
        MockerState::addCondition(
            'PHPForge\\Debug\\Storage',
            'tempnam',
            [],
            false,
            true,
        );

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'Unable to write temporary debug data file',
        );

        $summary = $this->summary('blocked', 1_700_000_000.0);

        $this->store()->writeSnapshot(new DebugSnapshot($summary, [], []), 10);
    }

    public function testThrowInvalidConfigExceptionWhenTheTemporaryFileCannotBeWritten(): void
    {
        MockerState::addCondition(
            'PHPForge\\Debug\\Storage',
            'file_put_contents',
            [],
            false,
            true,
        );

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'Unable to write temporary debug data file',
        );

        $summary = $this->summary('blocked', 1_700_000_000.0);

        $this->store()->writeSnapshot(new DebugSnapshot($summary, [], []), 10);
    }

    /**
     * Creates an isolated temporary storage path.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . '/yii-debug-storage-' . uniqid('', true);
    }

    /**
     * Removes the temporary storage directory.
     */
    protected function tearDown(): void
    {
        FileHelper::removeDirectory($this->path);

        parent::tearDown();
    }

    /**
     * Creates a store for the isolated temporary path.
     *
     * @return SnapshotStore Store configured for the current test.
     */
    private function store(): SnapshotStore
    {
        return new SnapshotStore($this->path, 0o777, null);
    }

    /**
     * Creates representative request metadata.
     *
     * @param string $tag Request tag.
     * @param float $time Request start timestamp.
     *
     * @return RequestSummary Representative request metadata.
     */
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
