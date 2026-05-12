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
use function array_merge;
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
 * Captures the per-request snapshot consumed by the debug toolbar: collects panel data, serializes it to disk and
 * maintains the rolling `index.data` manifest.
 */
class LogTarget extends Target
{
    public Module $module;
    public string $tag = '';

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(Module $module, array $config = [])
    {
        parent::__construct($config);

        $this->module = $module;
        $this->tag = uniqid();
    }

    /**
     * @param array<array-key, mixed> $messages
     */
    public function collect($messages, $final): void
    {
        $this->messages = array_merge($this->messages, $messages);

        if ($final) {
            $this->export();
        }
    }

    /**
     * @throws Exception
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
     * @see DefaultController
     *
     * @return array<string, array<string, mixed>>
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
     * @see DefaultController
     *
     * @return array<string, mixed>
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
            if (isset($normalized[$id]) && is_string($normalized[$id])) {
                $panel->tag = $tag;
                $panel->load(@unserialize($normalized[$id]));
            } else {
                unset($this->module->panels[$id]);
            }

            if (isset($exceptions[$id]) && $exceptions[$id] instanceof FlattenException) {
                $panel->setError($exceptions[$id]);
            }
        }

        return $normalized;
    }

    /**
     * Collects summary data of current request.
     *
     * @return array<string, mixed>
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
     * Removes obsolete data files.
     *
     * @param array<string, array<string, mixed>> $manifest
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
     * Returns the number of excessive Database caller(s).
     */
    protected function getExcessiveDbCallersCount(): int
    {
        $panel = $this->module->panels['db'] ?? null;

        return $panel instanceof DbPanel ? $panel->getExcessiveCallersCount() : 0;
    }

    /**
     * Returns total sql count executed in current request; `0` when the database panel is not configured.
     */
    protected function getSqlTotalCount(): int
    {
        $panel = $this->module->panels['db'] ?? null;

        if (!$panel instanceof DbPanel) {
            return 0;
        }

        // / 2 because messages are in couple (begin/end)
        return (int) (count($panel->getProfileLogs()) / 2);
    }

    /**
     * Removes stale data files (files not in the current index file — can happen due to a corrupted or rotated index).
     *
     * @param array<string, array<string, mixed>> $manifest
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
     * Narrows a raw deserialized manifest into `array<string, array<string, mixed>>`.
     *
     * Drops entries whose tag or inner key is non-string; returns `[]` when the input is not an array.
     *
     * @return array<string, array<string, mixed>>
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
     * Updates index file with summary log data.
     *
     * @param string $indexFile Path to index file.
     * @param array<string, mixed> $summary Summary log data.
     *
     * @throws InvalidConfigException
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
