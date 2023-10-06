<?php

declare(strict_types=1);

namespace yii\debug;

use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\console\Request;
use yii\console\Response;
use yii\debug\panels\DbPanel;
use yii\helpers\FileHelper;
use yii\log\Target;

use function array_diff;
use function array_keys;
use function array_map;
use function array_merge;
use function array_reverse;

/**
 * The debug LogTarget is used to store logs for later use in the debugger tool
 */
class LogTarget extends Target
{
    /**
     * @var Module
     */
    public Module $module;
    /**
     * @var string
     */
    public string $tag;

    /**
     * @param Module $module
     * @param array $config
     */
    public function __construct($module, array $config = [])
    {
        parent::__construct($config);
        $this->module = $module;
        $this->tag = uniqid();
    }

    /**
     * Exports log messages to a specific destination.
     * Child classes must implement this method.
     *
     * @throws Exception
     */
    public function export(): void
    {
        $path = $this->module->dataPath;
        FileHelper::createDirectory($path, $this->module->dirMode);

        $summary = $this->collectSummary();
        $dataFile = "$path/$this->tag.data";
        $data = [];
        $exceptions = [];
        foreach ($this->module->panels as $id => $panel) {
            try {
                $panelData = $panel->save();
                if ($id === 'profiling') {
                    $summary['peakMemory'] = $panelData['memory'];
                    $summary['processingTime'] = $panelData['time'];
                }
                $data[$id] = serialize($panelData);
            } catch (\Exception $exception) {
                $exceptions[$id] = new FlattenException($exception);
            }
        }
        $data['summary'] = $summary;
        $data['exceptions'] = $exceptions;

        file_put_contents($dataFile, serialize($data));
        if ($this->module->fileMode !== null) {
            @chmod($dataFile, $this->module->fileMode);
        }

        $indexFile = "$path/index.data";
        $this->updateIndexFile($indexFile, $summary);
    }

    /**
     * @see DefaultController
     *
     * @return array
     */
    public function loadManifest(): array
    {
        $indexFile = $this->module->dataPath . '/index.data';

        $content = '';
        $fp = @fopen($indexFile, 'r');
        if ($fp !== false) {
            @flock($fp, LOCK_SH);
            $content = fread($fp, filesize($indexFile));
            @flock($fp, LOCK_UN);
            fclose($fp);
        }

        if ($content !== '') {
            return array_reverse(unserialize($content), true);
        }

        return [];
    }

    /**
     * @see DefaultController
     *
     * @return array
     */
    public function loadTagToPanels($tag): array
    {
        $dataFile = $this->module->dataPath . "/$tag.data";

        $data = unserialize(file_get_contents($dataFile));

        $exceptions = $data['exceptions'];

        foreach ($this->module->panels as $id => $panel) {
            if (isset($data[$id])) {
                $panel->tag = $tag;
                $panel->load(unserialize($data[$id]));
            } else {
                unset($this->module->panels[$id]);
            }
            if (isset($exceptions[$id])) {
                $panel->setError($exceptions[$id]);
            }
        }

        return $data;
    }

    /**
     * Updates index file with summary log data
     *
     * @param string $indexFile path to index file
     * @param array $summary summary log data
     *
     * @throws InvalidConfigException
     */
    private function updateIndexFile(string $indexFile, array $summary): void
    {
        if (!@touch($indexFile) || ($fp = @fopen($indexFile, 'r+')) === false) {
            throw new InvalidConfigException("Unable to open debug data index file: $indexFile");
        }
        @flock($fp, LOCK_EX);
        $manifest = '';
        while (($buffer = fgets($fp)) !== false) {
            $manifest .= $buffer;
        }
        if (!feof($fp) || empty($manifest)) {
            // error while reading index data, ignore and create new
            $manifest = [];
        } else {
            $manifest = unserialize($manifest);
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

    /**
     * Processes the given log messages.
     * This method will filter the given messages with [[levels]] and [[categories]].
     * And if requested, it will also export the filtering result to a specific medium (e.g. email).
     *
     * @param array $messages log messages to be processed. See [[\yii\log\Logger::messages]] for the structure
     * of each message.
     * @param bool $final whether this method is called at the end of the current application
     *
     * @throws Exception
     */
    public function collect($messages, $final): void
    {
        $this->messages = array_merge($this->messages, $messages);
        if ($final) {
            $this->export();
        }
    }

    /**
     * Removes obsolete data files
     *
     * @param array $manifest
     */
    protected function gc(array &$manifest): void
    {
        if (count($manifest) > $this->module->historySize + 10) {
            $n = count($manifest) - $this->module->historySize;
            foreach (array_keys($manifest) as $tag) {
                $file = $this->module->dataPath . "/$tag.data";
                @unlink($file);
                if (isset($manifest[$tag]['mailFiles'])) {
                    foreach ($manifest[$tag]['mailFiles'] as $mailFile) {
                        @unlink(Yii::getAlias($this->module->panels['mail']->mailPath) . "/$mailFile");
                    }
                }
                unset($manifest[$tag]);
                if (--$n <= 0) {
                    break;
                }
            }
            $this->removeStaleDataFiles($manifest);
        }
    }

    /**
     * Remove staled data files i.e., files that are not in the current index file (may happen because of corrupted or
     * rotated index file)
     *
     * @param array $manifest
     */
    protected function removeStaleDataFiles(array $manifest): void
    {
        $storageTags = array_map(
            static function ($file) {
                return pathinfo($file, PATHINFO_FILENAME);
            },
            FileHelper::findFiles($this->module->dataPath, ['except' => ['index.data']])
        );

        $staledTags = array_diff($storageTags, array_keys($manifest));

        foreach ($staledTags as $tag) {
            @unlink($this->module->dataPath . "/$tag.data");
        }
    }

    /**
     * Collects summary data of the current request.
     *
     * @return array
     */
    protected function collectSummary(): array
    {
        if (Yii::$app === null) {
            return [];
        }

        $request = Yii::$app->getRequest();
        $response = Yii::$app->getResponse();
        $summary = [
            'tag' => $this->tag,
            'url' => $request instanceof Request ? 'php yii ' . implode(' ', $request->getParams()) : $request->getAbsoluteUrl(),
            'ajax' => $request instanceof Request ? 0 : (int) $request->getIsAjax(),
            'method' => $request instanceof Request ? 'COMMAND' : $request->getMethod(),
            'ip' => $request instanceof Request ? exec('whoami') : $request->getUserIP(),
            'time' => $_SERVER['REQUEST_TIME_FLOAT'],
            'statusCode' => $response instanceof Response ? $response->exitStatus : $response->statusCode,
            'sqlCount' => $this->getSqlTotalCount(),
            'excessiveCallersCount' => $this->getExcessiveDbCallersCount(),
        ];

        if (isset($this->module->panels['mail'])) {
            $mailFiles = $this->module->panels['mail']->getMessagesFileName();
            $summary['mailCount'] = count($mailFiles);
            $summary['mailFiles'] = $mailFiles;
        }

        return $summary;
    }

    /**
     * Returns total sql count executed in current request. If database panel is not configured
     * returns 0.
     *
     * @return float|int
     */
    protected function getSqlTotalCount(): float|int
    {
        if (!isset($this->module->panels['db'])) {
            return 0;
        }
        $profileLogs = $this->module->panels['db']->getProfileLogs();

        // / 2 because messages are in couple (begin/end)

        return count($profileLogs) / 2;
    }

    /**
     * Get the number of excessive Database caller(s).
     *
     * @return int
     *
     * @since 2.1.23
     */
    protected function getExcessiveDbCallersCount(): int
    {
        if (!isset($this->module->panels['db'])) {
            return 0;
        }
        /** @var DbPanel $dbPanel */
        $dbPanel = $this->module->panels['db'];

        return $dbPanel->getExcessiveCallersCount();
    }
}
