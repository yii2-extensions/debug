<?php

declare(strict_types=1);

namespace yii\debug;

use Yii;
use yii\base\{Exception, InvalidConfigException};
use yii\debug\helpers\Coerce;
use yii\debug\panels\{DbPanel, MailPanel};
use yii\helpers\FileHelper;
use yii\log\Target;

use function array_diff;
use function array_keys;
use function array_reverse;
use function chmod;
use function count;
use function fclose;
use function feof;
use function fgets;
use function file_get_contents;
use function file_put_contents;
use function filesize;
use function flock;
use function fopen;
use function fread;
use function ftruncate;
use function fwrite;
use function is_array;
use function is_string;
use function microtime;
use function pathinfo;
use function rewind;
use function serialize;
use function touch;
use function uniqid;
use function unlink;
use function unserialize;

/**
 * Per-request snapshot collector consumed by the debug toolbar.
 *
 * Serializes every registered panel's payload to a dedicated `<tag>.data` file under {@see Module::$dataPath} and
 * appends a summary row to the rolling `index.data` manifest. Old entries are evicted by {@see gc()} once the manifest
 * grows past {@see Module::$historySize}.
 *
 * The unique request {@see $tag} generated in the constructor wires the active request to its data file and to the
 * toolbar reference rendered by {@see Module}.
 */
class LogTarget extends Target
{
    /**
     * Debug module owning this target.
     */
    public Module $module;
    /**
     * Unique tag identifying the current request, generated in {@see __construct()}.
     */
    public string $tag = '';

    /**
     * Creates a log target bound to the given debug module and generates a unique tag for the current request.
     *
     * @param Module $module Debug module owning this target.
     * @param array<string, mixed> $config Target configuration forwarded to {@see Target::__construct()}.
     */
    public function __construct(Module $module, array $config = [])
    {
        parent::__construct($config);

        $this->module = $module;
        $this->tag = uniqid();
    }

    /**
     * Appends log messages to the internal buffer and flushes the request snapshot when `$final` is `true`.
     *
     * @param array<array-key, mixed> $messages Log messages captured during the request.
     * @param bool $final `true` when this is the final flush at request end; triggers {@see export()}.
     */
    public function collect($messages, $final): void
    {
        $this->messages = [...$this->messages, ...$messages];

        if ($final) {
            $this->export();
        }
    }

    /**
     * Persists the per-panel payloads and summary for the current request.
     *
     * Creates {@see Module::$dataPath} when missing, serializes every registered panel's `save()` return into a single
     * `<tag>.data` file (capturing any thrown {@see Exception} as a {@see FlattenException} so the request still
     * completes), then appends the summary row to the rolling `index.data` manifest.
     *
     * @throws Exception When the data directory cannot be created.
     */
    public function export(): void
    {
        $path = $this->module->dataPath;

        FileHelper::createDirectory($path, $this->module->dirMode);

        $summary = $this->collectSummary();

        $dataFile = "{$path}/{$this->tag}.data";
        $data = [];
        $exceptions = [];

        foreach ($this->module->panels as $id => $panel) {
            try {
                $panelData = $panel->save();

                if ($id === 'profiling' && is_array($panelData)) {
                    $summary['peakMemory'] = $panelData['memory'] ?? 0;
                    $summary['processingTime'] = $panelData['time'] ?? 0.0;
                }

                $data[$id] = serialize($panelData);
            } catch (Exception $exception) {
                $exceptions[$id] = new FlattenException($exception);
            }
        }

        $data['summary'] = $summary;
        $data['exceptions'] = $exceptions;

        file_put_contents($dataFile, serialize($data));

        if ($this->module->fileMode !== null) {
            @chmod($dataFile, $this->module->fileMode);
        }

        $indexFile = "{$path}/index.data";

        $this->updateIndexFile($indexFile, $summary);
    }

    /**
     * Reads and reverses the rolling `index.data` manifest so the most recent request appears first.
     *
     * @see DefaultController
     *
     * @return array<string, array<string, mixed>> Manifest entries keyed by request tag, ordered newest-first; `[]`
     * when the manifest is missing, empty, or corrupted.
     */
    public function loadManifest(): array
    {
        $indexFile = $this->module->dataPath . '/index.data';

        $content = '';

        $fp = @fopen($indexFile, 'r');

        if ($fp !== false) {
            @flock($fp, LOCK_SH);
            $size = filesize($indexFile);

            if ($size > 0) {
                $read = fread($fp, $size);
                $content = $read === false ? '' : $read;
            }

            @flock($fp, LOCK_UN);
            fclose($fp);
        }

        if ($content === '') {
            return [];
        }

        $manifest = @unserialize($content);

        if (!is_array($manifest)) {
            return [];
        }

        return array_reverse(self::narrowManifestEntries($manifest), true);
    }

    /**
     * Hydrates the registered panels from a previously persisted `<tag>.data` file.
     *
     * Each panel keyed in the saved payload receives its serialized data via {@see Panel::load()}; panels that
     * produced an exception during the original request are flagged via {@see Panel::setError()} so the controller can
     * render the error view. Panels neither present in the payload nor flagged with an exception are dropped from
     * {@see Module::$panels}, because they were added or removed between requests.
     *
     * @see DefaultController
     *
     * @param string $tag Request tag identifying the data file to load.
     *
     * @return array<string, mixed> Raw deserialized payload keyed by panel id, plus the `summary` and `exceptions`
     * entries.
     */
    public function loadTagToPanels(string $tag): array
    {
        $dataFile = $this->module->dataPath . "/{$tag}.data";

        $raw = @file_get_contents($dataFile);
        $data = $raw === false ? [] : @unserialize($raw);

        if (!is_array($data)) {
            $data = [];
        }

        $exceptions = is_array($data['exceptions'] ?? null) ? $data['exceptions'] : [];

        $normalized = [];

        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        foreach ($this->module->panels as $id => $panel) {
            $hasError = isset($exceptions[$id]) && $exceptions[$id] instanceof FlattenException;

            if (isset($normalized[$id]) && is_string($normalized[$id])) {
                $panel->tag = $tag;
                $panel->load(@unserialize($normalized[$id]));
            } elseif ($hasError === false) {
                unset($this->module->panels[$id]);
            }

            if ($hasError) {
                $panel->setError($exceptions[$id]);
            }
        }

        return $normalized;
    }

    /**
     * Collects the request-summary row recorded in the manifest.
     *
     * Captures URL, HTTP method, AJAX flag, client IP, request start time (`$_SERVER['REQUEST_TIME_FLOAT']` with
     * `microtime(true)` fallback for long-running runtimes such as RoadRunner or FrankenPHP where the global value is
     * stale), response status code, SQL query count, excessive DB caller count, and mail message metadata when the
     * Mail panel is registered.
     *
     * @return array<string, mixed> Summary row keyed by attribute name.
     */
    protected function collectSummary(): array
    {
        $request = Yii::$app->getRequest();
        $response = Yii::$app->getResponse();

        $requestTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);

        $userIP = $request->getUserIP();

        $summary = [
            'tag' => $this->tag,
            'url' => $request->getAbsoluteUrl(),
            'ajax' => (int) $request->getIsAjax(),
            'method' => $request->getMethod(),
            'ip' => $userIP === null ? '' : $userIP,
            'time' => $requestTime,
            'statusCode' => $response->statusCode,
            'sqlCount' => $this->getSqlTotalCount(),
            'excessiveCallersCount' => $this->getExcessiveDbCallersCount(),
        ];

        $mailPanel = $this->module->panels['mail'] ?? null;

        if ($mailPanel instanceof MailPanel) {
            $mailFiles = $mailPanel->getMessagesFileName();

            $summary['mailCount'] = count($mailFiles);
            $summary['mailFiles'] = $mailFiles;
        }

        return $summary;
    }

    /**
     * Evicts the oldest manifest entries (and their data files) when the manifest exceeds {@see Module::$historySize}.
     *
     * Tolerance of `+10` over the configured size avoids running the garbage collector on every request; once the
     * threshold is crossed, the surplus is removed in a single pass. Associated mail message files (when present in the
     * summary) are removed alongside the request data file.
     *
     * @param array<string, array<string, mixed>> $manifest Manifest passed by reference and mutated in place.
     */
    protected function gc(array &$manifest): void
    {
        if (count($manifest) <= $this->module->historySize + 10) {
            return;
        }

        $n = count($manifest) - $this->module->historySize;

        $mailPanel = $this->module->panels['mail'] ?? null;

        $mailPath = $mailPanel instanceof MailPanel ? Yii::getAlias($mailPanel->mailPath) : '';

        foreach (array_keys($manifest) as $tag) {
            $file = $this->module->dataPath . "/{$tag}.data";

            @unlink($file);

            $mailFiles = $manifest[$tag]['mailFiles'] ?? null;

            if (is_array($mailFiles) && $mailPath !== '') {
                foreach ($mailFiles as $mailFile) {
                    if (is_string($mailFile)) {
                        @unlink("{$mailPath}/{$mailFile}");
                    }
                }
            }

            unset($manifest[$tag]);

            if (--$n <= 0) {
                break;
            }
        }

        $this->removeStaleDataFiles($manifest);
    }

    /**
     * Returns the number of database callers that exceeded the configured query threshold.
     *
     * @return int `0` when the database panel is not registered.
     */
    protected function getExcessiveDbCallersCount(): int
    {
        $panel = $this->module->panels['db'] ?? null;

        return $panel instanceof DbPanel ? $panel->getExcessiveCallersCount() : 0;
    }

    /**
     * Returns the total number of SQL queries executed during the request.
     *
     * Profile messages are recorded as begin/end pairs, so the raw message count is halved.
     *
     * @return int `0` when the database panel is not registered.
     */
    protected function getSqlTotalCount(): int
    {
        $panel = $this->module->panels['db'] ?? null;

        if (!$panel instanceof DbPanel) {
            return 0;
        }

        return (int) (count($panel->getProfileLogs()) / 2);
    }

    /**
     * Removes orphan `<tag>.data` files left behind when the index file was rotated or corrupted.
     *
     * @param array<string, array<string, mixed>> $manifest Authoritative manifest used to identify orphans.
     */
    protected function removeStaleDataFiles(array $manifest): void
    {
        $storageTags = [];

        foreach (FileHelper::findFiles($this->module->dataPath, ['except' => ['index.data']]) as $file) {
            if (is_string($file)) {
                $storageTags[] = pathinfo($file, PATHINFO_FILENAME);
            }
        }

        $staledTags = array_diff($storageTags, array_keys($manifest));

        foreach ($staledTags as $tag) {
            @unlink($this->module->dataPath . "/{$tag}.data");
        }
    }

    /**
     * Narrows a raw deserialized manifest into the typed `array<string, array<string, mixed>>` shape.
     *
     * Drops entries whose tag or inner key is non-string; returns `[]` when the input is not an array.
     *
     * @param mixed $manifest Raw value returned by {@see unserialize()}.
     *
     * @return array<string, array<string, mixed>> Typed manifest safe to iterate.
     */
    private static function narrowManifestEntries(mixed $manifest): array
    {
        if (!is_array($manifest)) {
            return [];
        }

        $normalized = [];

        foreach ($manifest as $tag => $entry) {
            if (is_string($tag) && is_array($entry)) {
                $normalized[$tag] = Coerce::stringKeyedArray($entry);
            }
        }

        return $normalized;
    }

    /**
     * Appends the current request summary to the rolling `index.data` manifest under an exclusive file lock.
     *
     * Reads the existing manifest (creating the file when missing), inserts the current `$tag => $summary` entry,
     * triggers {@see gc()} to enforce the history-size cap, and rewrites the file atomically.
     *
     * @param string $indexFile Absolute path to the manifest file.
     * @param array<string, mixed> $summary Summary row produced by {@see collectSummary()}.
     *
     * @throws InvalidConfigException When the manifest file cannot be opened for read/write.
     */
    private function updateIndexFile(string $indexFile, array $summary): void
    {
        if (!@touch($indexFile) || ($fp = @fopen($indexFile, 'r+')) === false) {
            throw new InvalidConfigException("Unable to open debug data index file: {$indexFile}");
        }

        @flock($fp, LOCK_EX);

        $serialized = '';

        while (($buffer = fgets($fp)) !== false) {
            $serialized .= $buffer;
        }

        $manifest = [];

        if (feof($fp) && $serialized !== '') {
            $manifest = self::narrowManifestEntries(@unserialize($serialized));
        }

        $manifest[$this->tag] = $summary;

        $this->gc($manifest);

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, serialize($manifest));
        @flock($fp, LOCK_UN);
        @fclose($fp);

        if ($this->module->fileMode !== null) {
            @chmod($indexFile, $this->module->fileMode);
        }
    }
}
