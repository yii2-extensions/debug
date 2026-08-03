<?php

declare(strict_types=1);

namespace yii\debug;

use Override;
use Throwable;
use Yii;
use yii\base\Exception;
use yii\debug\panels\{DbPanel, MailPanel};
use yii\debug\panels\profile\ProfilingSnapshot;
use yii\debug\storage\{
    DebugSnapshot,
    PanelFailure,
    RequestSummary,
    SnapshotStore,
};
use yii\log\Target;

use function array_values;
use function count;
use function is_float;
use function is_int;
use function microtime;
use function uniqid;
use function unlink;

/**
 * Per-request JSON snapshot collector consumed by the debug toolbar.
 */
class LogTarget extends Target
{
    /**
     * Unique tag identifying the current request.
     */
    public string $tag = '';

    /**
     * Memoized snapshot store bound to the module's data path, instantiated lazily by {@see store()}.
     */
    private SnapshotStore|null $store = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(public Module $module, array $config = [])
    {
        parent::__construct($config);
        $this->tag = uniqid();
    }

    /**
     * @param array<array-key, mixed> $messages
     */
    #[Override]
    public function collect($messages, $final): void
    {
        $this->messages = [...$this->messages, ...$messages];

        if ($final) {
            $this->export();
        }
    }

    /**
     * Captures every panel into a typed DTO, writes one JSON snapshot, and updates the JSON manifest.
     *
     * A failing panel is isolated as a {@see PanelFailure}; root, encoding, and filesystem failures remain explicit.
     *
     * @throws Exception When the debug data directory cannot be created.
     */
    public function export(): void
    {
        $summary = $this->collectSummary();
        $panels = [];
        $failures = [];

        foreach ($this->module->panels as $id => $panel) {
            try {
                $panelSnapshot = $panel->capture();

                if ($panelSnapshot !== null) {
                    $panels[$id] = $panelSnapshot->jsonSerialize();
                }

                if ($panelSnapshot instanceof ProfilingSnapshot) {
                    $summary = $summary->withProfiling(
                        $panelSnapshot->time,
                        $panelSnapshot->memory,
                    );
                }
            } catch (Throwable $throwable) {
                $failures[$id] = PanelFailure::fromThrowable(PanelFailure::CAPTURE, $throwable);
            }
        }

        $store = $this->store();
        $store->writeSnapshot($this->tag, new DebugSnapshot($summary, $panels, $failures));

        foreach ($store->updateManifest($summary, $this->module->historySize) as $removed) {
            $this->removeMailFiles($removed);
        }
    }

    /**
     * @return array<string, RequestSummary> Manifest entries keyed by tag, newest first.
     */
    public function loadManifest(): array
    {
        return $this->store()->loadManifest();
    }

    /**
     * Hydrates all registered panels for a tag and returns its typed request summary.
     *
     * Invalid root JSON or summary data rejects the snapshot. Invalid panel payloads are isolated as visible panel
     * errors while the remaining panels continue to load.
     */
    public function loadTagToPanels(string $tag): RequestSummary|null
    {
        $snapshot = $this->store()->readSnapshot($tag);

        if ($snapshot === null) {
            return null;
        }

        foreach ($this->module->panels as $id => $panel) {
            $failure = $snapshot->failures[$id] ?? null;

            if (isset($snapshot->panels[$id])) {
                $panel->tag = $tag;

                try {
                    $panel->hydrate($snapshot->panels[$id]);
                } catch (Throwable $throwable) {
                    $failure = PanelFailure::fromThrowable(PanelFailure::HYDRATE, $throwable);
                }
            } elseif ($failure === null) {
                unset($this->module->panels[$id]);

                continue;
            }

            if ($failure !== null) {
                $panel->tag = $tag;
                $panel->setError($failure->exception);
            }
        }

        return $snapshot->summary;
    }

    /**
     * Captures the canonical manifest summary for the current request.
     */
    protected function collectSummary(): RequestSummary
    {
        $request = Yii::$app->getRequest();
        $response = Yii::$app->getResponse();
        $requestTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        $mailFiles = [];

        $mailPanel = $this->module->panels['mail'] ?? null;

        if ($mailPanel instanceof MailPanel) {
            $mailFiles = $mailPanel->getMessagesFileName();
        }

        return new RequestSummary(
            tag: $this->tag,
            url: $request->getAbsoluteUrl(),
            ajax: $request->getIsAjax(),
            method: $request->getMethod(),
            ip: $request->getUserIP() ?? '',
            time: is_int($requestTime) || is_float($requestTime) ? $requestTime : microtime(true),
            statusCode: $response->statusCode,
            sqlCount: $this->getSqlTotalCount(),
            excessiveCallersCount: $this->getExcessiveDbCallersCount(),
            mailCount: count($mailFiles),
            mailFiles: array_values($mailFiles),
            processingTime: null,
            peakMemory: null,
        );
    }

    protected function getExcessiveDbCallersCount(): int
    {
        $panel = $this->module->panels['db'] ?? null;

        return $panel instanceof DbPanel ? $panel->getExcessiveCallersCount() : 0;
    }

    protected function getSqlTotalCount(): int
    {
        $panel = $this->module->panels['db'] ?? null;

        return $panel instanceof DbPanel ? (int) (count($panel->getProfileLogs()) / 2) : 0;
    }

    private function removeMailFiles(RequestSummary $summary): void
    {
        $mailPanel = $this->module->panels['mail'] ?? null;

        if (!$mailPanel instanceof MailPanel) {
            return;
        }

        $mailPath = Yii::getAlias($mailPanel->mailPath);

        foreach ($summary->mailFiles as $file) {
            @unlink("{$mailPath}/{$file}");
        }
    }

    private function store(): SnapshotStore
    {
        return $this->store ??= new SnapshotStore(
            $this->module->dataPath,
            $this->module->dirMode,
            $this->module->fileMode,
        );
    }
}
