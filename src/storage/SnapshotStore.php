<?php

declare(strict_types=1);

namespace yii\debug\storage;

use JsonException;
use Throwable;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;

use function array_diff;
use function array_keys;
use function array_reverse;
use function chmod;
use function count;
use function fclose;
use function file_get_contents;
use function flock;
use function fopen;
use function glob;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;
use function pathinfo;
use function preg_match;
use function rename;
use function unlink;

/**
 * Owns the JSON filesystem boundary, manifest locking, atomic writes, and snapshot garbage collection.
 */
final class SnapshotStore
{
    private const string INDEX_FILE = 'index.json';
    private const int JSON_FLAGS = JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION;
    private const string LOCK_FILE = 'index.lock';

    /**
     * Tracks whether the data directory has already been created and swept of pre-JSON `*.data` files.
     */
    private bool $initialized = false;

    public function __construct(
        private readonly string $path,
        private readonly int $dirMode,
        private readonly int|null $fileMode,
    ) {}

    /**
     * @return array<string, RequestSummary> Newest entries first.
     */
    public function loadManifest(): array
    {
        $lock = @fopen($this->lockFile(), 'c+');

        if ($lock === false) {
            return [];
        }

        @flock($lock, LOCK_SH);
        $manifest = $this->readManifestFile();
        @flock($lock, LOCK_UN);
        fclose($lock);

        return $manifest === null ? [] : array_reverse($manifest->entries, true);
    }

    public function readSnapshot(string $tag): DebugSnapshot|null
    {
        if (!self::isValidTag($tag)) {
            return null;
        }

        $raw = @file_get_contents($this->snapshotFile($tag));

        if ($raw === false || $raw === '') {
            return null;
        }

        try {
            return DebugSnapshot::fromArray(self::decode($raw));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Adds a summary and returns entries evicted by history garbage collection.
     *
     * @return list<RequestSummary>
     */
    public function updateManifest(RequestSummary $summary, int $historySize): array
    {
        $this->initialize();
        $lock = @fopen($this->lockFile(), 'c+');

        if ($lock === false) {
            throw new InvalidConfigException("Unable to open debug data lock file: {$this->lockFile()}");
        }

        @flock($lock, LOCK_EX);

        try {
            $manifest = $this->readManifestFile();
            $resetStorage = $manifest === null && is_file($this->indexFile());
            $entries = $manifest instanceof Manifest ? $manifest->entries : [];
            $entries[$summary->tag] = $summary;
            $removed = $this->collectGarbage($entries, $historySize);

            $this->atomicWrite($this->indexFile(), self::encode(new Manifest($entries)));

            if ($resetStorage) {
                $this->removeStaleSnapshots($entries);
            }
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $removed;
    }

    public function writeSnapshot(string $tag, DebugSnapshot $snapshot): void
    {
        $this->initialize();
        $this->atomicWrite($this->snapshotFile($tag), self::encode($snapshot));
    }

    private function atomicWrite(string $file, string $contents): void
    {
        $temporary = @tempnam($this->path, '.debug-');

        if ($temporary === false) {
            throw new InvalidConfigException("Unable to write temporary debug data file for: {$file}");
        }

        if (file_put_contents($temporary, $contents) === false) {
            @unlink($temporary);

            throw new InvalidConfigException("Unable to write temporary debug data file for: {$file}");
        }

        if ($this->fileMode !== null) {
            @chmod($temporary, $this->fileMode);
        }

        if (!@rename($temporary, $file)) {
            @unlink($temporary);

            throw new InvalidConfigException("Unable to replace debug data file: {$file}");
        }
    }

    /**
     * @param array<string, RequestSummary> $entries
     * @return list<RequestSummary>
     */
    private function collectGarbage(array &$entries, int $historySize): array
    {
        if (count($entries) <= $historySize + 10) {
            return [];
        }

        $remaining = count($entries) - $historySize;
        $removed = [];

        foreach (array_keys($entries) as $tag) {
            $removed[] = $entries[$tag];
            @unlink($this->snapshotFile($tag));
            unset($entries[$tag]);

            if (--$remaining <= 0) {
                break;
            }
        }

        $this->removeStaleSnapshots($entries);

        return $removed;
    }

    /**
     * @throws JsonException
     */
    private static function decode(string $json): mixed
    {
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws JsonException
     */
    private static function encode(mixed $value): string
    {
        return json_encode($value, self::JSON_FLAGS);
    }

    private function indexFile(): string
    {
        return $this->path . '/' . self::INDEX_FILE;
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        FileHelper::createDirectory($this->path, $this->dirMode);
        $legacy = glob($this->path . '/*.data');

        foreach ($legacy === false ? [] : $legacy as $file) {
            @unlink($file);
        }

        $this->initialized = true;
    }

    private static function isValidTag(string $tag): bool
    {
        return $tag !== '' && preg_match('/\A[A-Za-z0-9._-]+\z/D', $tag) === 1;
    }

    private function lockFile(): string
    {
        return $this->path . '/' . self::LOCK_FILE;
    }

    private function readManifestFile(): Manifest|null
    {
        $raw = @file_get_contents($this->indexFile());

        if ($raw === false || $raw === '') {
            return new Manifest([]);
        }

        try {
            return Manifest::fromArray(self::decode($raw));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, RequestSummary> $entries
     */
    private function removeStaleSnapshots(array $entries): void
    {
        $storedTags = [];

        foreach (FileHelper::findFiles($this->path, ['only' => ['*.json'], 'except' => [self::INDEX_FILE]]) as $file) {
            if (is_string($file)) {
                $storedTags[] = pathinfo($file, PATHINFO_FILENAME);
            }
        }

        foreach (array_diff($storedTags, array_keys($entries)) as $tag) {
            @unlink($this->snapshotFile($tag));
        }
    }

    private function snapshotFile(string $tag): string
    {
        if (!self::isValidTag($tag)) {
            throw new InvalidConfigException("Invalid debug snapshot tag: {$tag}");
        }

        return $this->path . "/{$tag}.json";
    }
}
