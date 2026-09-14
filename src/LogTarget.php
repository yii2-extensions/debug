<?php

declare(strict_types=1);

namespace yii\debug;

use Override;
use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Storage\{DebugSnapshot, PanelFailure, RequestSummary};
use Throwable;
use Yii;
use yii\base\Exception;
use yii\debug\collectors\{DbCollector, MailCollector};
use yii\debug\panels\JsonPanel;
use yii\debug\storage\SnapshotStore;
use yii\log\Target;

use function array_push;
use function array_values;
use function bin2hex;
use function count;
use function is_array;
use function is_float;
use function is_int;
use function microtime;
use function random_bytes;

/**
 * Per-request JSON snapshot collector consumed by the debug toolbar.
 *
 * @phpstan-import-type LogMessage from \yii\log\Logger
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
     * Adopts the owning module and registers itself as the module's log target when none is wired yet, so collectors
     * reading through {@see Module::$logTarget} see the messages this target accumulates.
     *
     * @param Module $module Debugger module owning this target.
     * @param array<string, mixed> $config Standard {@see \yii\base\BaseObject} configuration.
     */
    public function __construct(public Module $module, array $config = [])
    {
        parent::__construct($config);

        $this->tag = bin2hex(random_bytes(16));

        if (!$module->logTarget instanceof self) {
            $module->logTarget = $this;
        }
    }

    /**
     * Starts a fresh persistent-worker request with an isolated tag and message buffer.
     */
    public function beginRequest(): void
    {
        $this->tag = bin2hex(random_bytes(16));

        $this->messages = [];
    }

    /**
     * Accumulates the flushed log messages and writes the capture once the request ends.
     *
     * @param array<int|string, LogMessage> $messages Messages flushed by the logger.
     * @param bool $final Whether this is the final flush of the request.
     */
    #[Override]
    public function collect($messages, $final): void
    {
        array_push($this->messages, ...array_values($messages));

        if ($final) {
            $this->export();
        }
    }

    /**
     * Captures every panel into a typed DTO, writes one JSON snapshot, and updates the JSON manifest.
     *
     * A failing panel is isolated as a {@see PanelFailure}; root, encoding, and filesystem failures remain explicit.
     *
     * @throws Exception when the debug data directory cannot be created.
     */
    public function export(): void
    {
        $summary = $this->collectSummary();

        $coordinator = $this->module->getCollectorCoordinator();

        $coordinator->run(function () use ($coordinator, $summary): void {
            $collectorSnapshot = $coordinator->capture($summary);

            $panels = $collectorSnapshot->panels;
            $failures = $collectorSnapshot->failures;

            $profilingPayload = $panels['profiling'] ?? null;

            if (is_array($profilingPayload)) {
                $time = Coerce::floatOrNull($profilingPayload['time'] ?? null);
                $memory = Coerce::intOrNull($profilingPayload['memory'] ?? null);

                if ($time !== null && $memory !== null) {
                    $summary = $summary->withProfiling($time, $memory);
                }
            }

            foreach ($this->module->panels as $id => $panel) {
                if ($coordinator->hasCollector($id)) {
                    continue;
                }

                try {
                    $panelSnapshot = $panel->capture();

                    if ($panelSnapshot !== null) {
                        $panels[$id] = \PHPForge\Debug\Storage\Json::payload($panelSnapshot->jsonSerialize());
                    }
                } catch (Throwable $throwable) {
                    $failures[$id] = PanelFailure::fromThrowable(
                        PanelFailure::CAPTURE,
                        $throwable,
                    );
                }
            }

            $store = $this->store();

            try {
                $result = $store->writeSnapshotResult(
                    new DebugSnapshot($summary, $panels, $failures),
                    $this->module->historySize,
                );
            } catch (Throwable $failure) {
                $this->removeMailFiles($summary);

                throw $failure;
            }

            foreach ($result->removed as $removedSummary) {
                $this->removeMailFiles($removedSummary);
            }

            $this->reconcileMailFiles($result->entries);
        });
    }

    /**
     * Reads the manifest of retained captures.
     *
     * @return array<string, RequestSummary> Manifest entries keyed by tag, newest first.
     */
    public function loadManifest(): array
    {
        return $this->store()->loadManifest();
    }

    /**
     * Returns the immutable snapshot envelope for a captured request.
     *
     * Unlike {@see loadTagToPanels()}, this method does not mutate the registered panel instances. It is intended for
     * cross-request workflows such as history comparison where two captures must be inspected at the same time.
     *
     * @param string $tag Captured request tag.
     *
     * @return DebugSnapshot|null Snapshot envelope, or `null` when the tag is unavailable.
     */
    public function loadSnapshot(string $tag): DebugSnapshot|null
    {
        return $this->store()->readSnapshot($tag);
    }

    /**
     * Hydrates all registered panels for a tag and returns its typed request summary.
     *
     * Invalid root JSON or summary data rejects the snapshot. Invalid panel payloads are isolated as visible panel
     * errors while the remaining panels continue to load.
     *
     * @param string $tag Capture to load.
     *
     * @return RequestSummary|null Summary of the capture, or `null` when it cannot be read.
     */
    public function loadTagToPanels(string $tag): RequestSummary|null
    {
        $snapshot = $this->store()->readSnapshot($tag);

        if ($snapshot === null) {
            return null;
        }

        foreach ($snapshot->panels as $id => $_payload) {
            $this->registerFallbackPanel($id);
        }

        foreach ($snapshot->failures as $id => $_failure) {
            $this->registerFallbackPanel($id);
        }

        foreach ($this->module->panels as $id => $panel) {
            $failure = $snapshot->failures[$id] ?? null;

            if (isset($snapshot->panels[$id])) {
                $panel->tag = $tag;

                try {
                    $panel->hydrate($snapshot->panels[$id]);
                } catch (Throwable $throwable) {
                    $failure = PanelFailure::fromThrowable(
                        PanelFailure::HYDRATE,
                        $throwable,
                    );
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
     *
     * @return RequestSummary Manifest entry describing the current request.
     */
    protected function collectSummary(): RequestSummary
    {
        $request = Yii::$app->getRequest();
        $response = Yii::$app->getResponse();

        $requestTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);

        $mailFiles = [];

        $mailCollector = $this->module->getCollectorCoordinator()->collector('mail');

        if ($mailCollector instanceof MailCollector) {
            $mailFiles = $mailCollector->getMessagesFileName();
        }

        return RequestSummary::create($this->tag)
            ->withRequest(
                $this->module->createCapturePolicy()->redactUrl($request->getAbsoluteUrl()),
                $request->getMethod(),
                $request->getUserIP() ?? '',
                is_int($requestTime) || is_float($requestTime) ? $requestTime : microtime(true),
                $request->getIsAjax(),
            )
            ->withResponse($response->statusCode)
            ->withDatabase($this->getSqlTotalCount(), $this->getExcessiveDbCallersCount())
            ->withMail(count($mailFiles), array_values($mailFiles));
    }

    /**
     * Counts the call sites flagged as issuing too many statements.
     *
     * @return int Number of call sites that issued at least the configured threshold of queries; `0` when the
     * Database collector is not registered.
     */
    protected function getExcessiveDbCallersCount(): int
    {
        $collector = $this->module->getCollectorCoordinator()->collector('db');

        return $collector instanceof DbCollector ? $collector->getExcessiveCallersCount() : 0;
    }

    /**
     * Counts the statements executed during the request.
     *
     * @return int Number of queries executed during the request; `0` when the Database collector is not
     * registered.
     */
    protected function getSqlTotalCount(): int
    {
        $collector = $this->module->getCollectorCoordinator()->collector('db');

        return $collector instanceof DbCollector ? (int) (count($collector->getProfileLogs()) / 2) : 0;
    }

    /**
     * Deletes the captured `.eml` files no retained capture refers to.
     *
     * @param array<string, RequestSummary>|null $entries Committed manifest entries, or `null` after a failed read.
     */
    private function reconcileMailFiles(array|null $entries): void
    {
        if ($entries === null) {
            return;
        }

        $mailCollector = $this->module->getCollectorCoordinator()->collector('mail');

        if (!$mailCollector instanceof MailCollector) {
            return;
        }

        $referencedFiles = [];

        foreach ($entries as $entry) {
            foreach ($entry->mailFiles as $file) {
                $referencedFiles[] = $file;
            }
        }

        $mailCollector->reconcileFiles($referencedFiles);
    }

    /**
     * Registers a raw JSON panel for a captured payload whose panel is no longer configured, so the data stays
     * readable instead of disappearing from the UI.
     *
     * @param string $id Panel id found in the capture.
     */
    private function registerFallbackPanel(string $id): void
    {
        if (isset($this->module->panels[$id])) {
            return;
        }

        if ($id === 'timeline' && $this->module->getCollectorCoordinator()->hasCollector($id) === false) {
            return;
        }

        if (
            ExtensionAvailability::isAvailable($id) === false
            && $this->module->getCollectorCoordinator()->hasCollector($id) === false
        ) {
            return;
        }

        $panel = new JsonPanel();

        $panel->id = $id;
        $panel->module = $this->module;

        $this->module->panels[$id] = $panel;
    }

    /**
     * Deletes the `.eml` files referenced by a capture that is being rotated out of the manifest.
     *
     * @param RequestSummary $summary Summary of the capture being discarded.
     */
    private function removeMailFiles(RequestSummary $summary): void
    {
        $mailCollector = $this->module->getCollectorCoordinator()->collector('mail');

        if (!$mailCollector instanceof MailCollector) {
            return;
        }

        $mailCollector->removeFiles($summary->mailFiles);
    }

    /**
     * Returns the snapshot store bound to the module's data path, creating it on first use.
     *
     * @return SnapshotStore Store reading and writing this module's captures.
     */
    private function store(): SnapshotStore
    {
        return $this->store ??= SnapshotStore::forModule($this->module);
    }
}
