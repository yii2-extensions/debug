<?php

declare(strict_types=1);

namespace yii\debug\storage;

use PHPForge\Debug\Storage\{
    DebugSnapshot,
    RequestSummary,
    SnapshotStore as CoreSnapshotStore,
    StorageException,
};
use yii\base\InvalidConfigException;

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
     * Returns manifest entries ordered from newest to oldest.
     *
     * Usage example:
     *
     * ```php
     * $entries = $store->loadManifest();
     * ```
     *
     * @return array<string, RequestSummary> Newest entries first.
     */
    public function loadManifest(): array
    {
        return $this->store->loadManifest();
    }

    /**
     * Returns a stored snapshot or `null` when unavailable.
     *
     * Usage example:
     *
     * ```php
     * $snapshot = $store->readSnapshot('request-1');
     * ```
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
     * Usage example:
     *
     * ```php
     * $removed = $store->writeSnapshot($snapshot, 50);
     * ```
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
            throw new InvalidConfigException($exception->getMessage(), previous: $exception);
        }
    }
}
