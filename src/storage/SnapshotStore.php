<?php

declare(strict_types=1);

namespace yii\debug\storage;

use PHPForge\Debug\Storage\{
    DebugSnapshot,
    ManifestReadResult,
    RequestSummary,
    SnapshotStore as CoreSnapshotStore,
    StorageException,
};
use ReflectionException;
use ReflectionMethod;
use yii\base\InvalidConfigException;
use yii\debug\Module;

/**
 * Yii2 compatibility facade for the framework-neutral snapshot store.
 */
final class SnapshotStore
{
    private readonly CoreSnapshotStore $store;

    /**
     * Creates a Yii2 facade for the framework-neutral store.
     *
     * @param string $path Storage directory path.
     * @param int $dirMode Directory permissions used when creating the storage path.
     * @param int|null $fileMode File permissions or `null` to preserve the system default.
     */
    public function __construct(string $path, int $dirMode, int|null $fileMode)
    {
        $this->store = new CoreSnapshotStore($path, $dirMode, $fileMode);
    }

    /**
     * Creates a store bound to the module's data path and permission settings.
     *
     * @param Module $module Debug module whose `dataPath`, `dirMode`, and `fileMode` configure the store.
     *
     * @return self Store bound to the module settings.
     */
    public static function forModule(Module $module): self
    {
        return new self($module->dataPath, $module->dirMode, $module->fileMode);
    }

    /**
     * Returns manifest entries ordered from newest to oldest.
     *
     * @return array<string, RequestSummary> Newest entries first.
     */
    public function loadManifest(): array
    {
        return $this->store->loadManifest();
    }

    /**
     * Returns manifest entries together with an optional core storage diagnostic.
     */
    public function loadManifestResult(): ManifestReadResult
    {
        return $this->store->loadManifestResult();
    }

    /**
     * Returns a stored snapshot or `null` when unavailable.
     *
     * @param string $tag Snapshot tag.
     *
     * @return DebugSnapshot|null Hydrated snapshot or `null` when unavailable.
     */
    public function readSnapshot(string $tag): DebugSnapshot|null
    {
        return $this->store->readSnapshot($tag);
    }

    /**
     * Writes a snapshot and manifest update through one core storage transaction.
     *
     * @param DebugSnapshot $snapshot Snapshot to persist.
     * @param int $historySize Maximum number of retained entries.
     *
     * @return list<RequestSummary> Entries evicted from the manifest.
     */
    public function writeSnapshot(DebugSnapshot $snapshot, int $historySize): array
    {
        try {
            return $this->store->writeSnapshot($snapshot, $historySize);
        } catch (StorageException $exception) {
            throw new InvalidConfigException(
                $exception->getMessage(),
                previous: $exception,
            );
        }
    }

    /**
     * Writes a snapshot and returns the committed manifest without a second read when supported by debug-core.
     *
     * The compatibility fallback keeps this adapter usable with the previous additive core API. It performs the
     * former follow-up read until the dependency is updated.
     *
     * @param DebugSnapshot $snapshot Snapshot to persist.
     * @param int $historySize Maximum number of retained entries.
     *
     * @return SnapshotWriteResult Committed entries and evictions.
     */
    public function writeSnapshotResult(DebugSnapshot $snapshot, int $historySize): SnapshotWriteResult
    {
        try {
            $writer = $this->coreResultWriter();

            if ($writer !== null) {
                /**
                 * @var object{
                 *     entries: array<string, RequestSummary>,
                 *     removed: list<RequestSummary>
                 * } $result
                 */
                $result = $writer->invoke($this->store, $snapshot, $historySize);

                return new SnapshotWriteResult($result->entries, $result->removed);
            }

            $removed = $this->store->writeSnapshot($snapshot, $historySize);
            $manifest = $this->store->loadManifestResult();

            return new SnapshotWriteResult(
                $manifest->error === null ? $manifest->entries : null,
                $removed,
            );
        } catch (StorageException $exception) {
            throw new InvalidConfigException(
                $exception->getMessage(),
                previous: $exception,
            );
        }
    }

    /**
     * Resolves the additive result API when the installed debug-core version provides it.
     */
    private function coreResultWriter(): ReflectionMethod|null
    {
        try {
            return new ReflectionMethod($this->store, 'writeSnapshotResult');
        } catch (ReflectionException) {
            return null;
        }
    }
}
