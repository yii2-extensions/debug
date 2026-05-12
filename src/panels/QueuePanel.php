<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Throwable;
use Yii;
use yii\base\Event;
use yii\debug\models\search\Queue as QueueSearch;
use yii\debug\Panel;
use yii\debug\panels\queue\{JobPayloadInspector, JobRecord, QueueDriverDetector};

use function array_values;
use function class_exists;
use function count;
use function get_class;
use function interface_exists;
use function is_array;
use function is_int;
use function is_object;
use function is_scalar;
use function is_string;
use function is_subclass_of;
use function microtime;
use function spl_object_id;

/**
 * Debugger panel that captures every queue lifecycle event triggered during the request; `afterPush`, `afterExec`,
 * `afterError`; emitted by any class extending the `yii\queue\Queue` base from `yiisoft/yii2-queue`.
 *
 * The panel does not depend on the `yiisoft/yii2-queue` package being installed: listeners are attached via
 * `Event::on()` using the queue base class FQCN as a string, so registration is a no-op when no queue package is
 * present and the empty-state view is shown.
 *
 * Usage example:
 *
 * ```php
 * Yii::$app->queue->push(new \app\jobs\HelloJob());
 * // -> the panel records a `push` event, visible in the debug toolbar
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
class QueuePanel extends Panel
{
    /**
     * Queue base class whose events are listened on; the abstract base `yii\queue\Queue` from `yiisoft/yii2-queue`
     * (≥ 2.0) every concrete driver extends.
     */
    private const QUEUE_BASE_CLASS = 'yii\\queue\\Queue';

    /**
     * Map of `spl_object_id($queueComponent) => component-id` populated lazily inside event listeners so each event's
     * sender can be matched back to its registered name in `Yii::$app->components`.
     *
     * @var array<int, string>
     */
    private array $componentIdCache = [];

    /**
     * Track exec start times keyed by `spl_object_id($job)` so the matching `afterExec` / `afterError` event can
     * compute the elapsed duration without depending on the queue driver.
     *
     * @var array<int, float>
     */
    private array $execStarts = [];

    /**
     * @var list<array{
     *   eventType: string,
     *   componentId: string,
     *   driverName: string,
     *   driverClass: string,
     *   isAsync: bool,
     *   jobClass: string,
     *   payloadFields: array<string, mixed>,
     *   time: float,
     *   jobId: string,
     *   ttr: int|null,
     *   delay: int|null,
     *   priority: int|null,
     *   attempt: int|null,
     *   duration: float|null,
     *   error: string,
     * }>
     */
    private array $records = [];

    public function getDetail(): string
    {
        $searchModel = new QueueSearch();

        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams(), $this->arrayRecords());

        return Yii::$app->view->render(
            'panels/queue/detail',
            [
                'dataProvider' => $dataProvider,
                'panel' => $this,
                'searchModel' => $searchModel,
            ],
        );
    }

    public function getName(): string
    {
        return 'Queue';
    }

    public function getToolbarIcon(): string
    {
        return 'queue';
    }

    public function init(): void
    {
        parent::init();

        $this->actions['queue-job'] = [
            'class' => 'yii\\debug\\actions\\queue\\JobAction',
            'panel' => $this,
        ];

        Event::on(self::QUEUE_BASE_CLASS, 'afterPush', $this->onPush(...));
        Event::on(self::QUEUE_BASE_CLASS, 'beforeExec', $this->onBeforeExec(...));
        Event::on(self::QUEUE_BASE_CLASS, 'afterExec', $this->onAfterExec(...));
        Event::on(self::QUEUE_BASE_CLASS, 'afterError', $this->onAfterError(...));
    }

    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * @return array{records: list<array<string, mixed>>}
     */
    public function save(): array
    {
        return [
            'records' => $this->records,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function getToolbarItems(): array|null
    {
        // The toolbar HTML is fetched via a separate AJAX call that recreates the panel from the saved snapshot; by
        // then `$this->records` is empty (event listeners fire only in the original request) and the captured data
        // lives in `$this->data['records']`. Read from whichever is populated so both call sites; live request render
        // and the toolbar replay request — see the same numbers.
        $records = $this->resolveRecords();

        // Hide the button entirely on apps that don't configure any queue component — keeps the toolbar tidy on hosts
        // that don't use yii2-queue at all. On apps that DO configure one or more queue components, surface the button
        // even when zero events were captured this request, mirroring how the Database / Logs / Events panels behave
        // (always present so the developer knows the panel exists).
        if ($records === [] && $this->hasQueueComponentConfigured() === false) {
            return null;
        }

        $errors = 0;

        foreach ($records as $record) {
            if (is_array($record) && ($record['eventType'] ?? null) === JobRecord::TYPE_ERROR) {
                $errors++;
            }
        }

        $items = [
            [
                'value' => count($records),
            ],
        ];

        if ($errors > 0) {
            $items[] = [
                'label' => 'Errors',
                'status' => 'danger',
                'value' => $errors,
            ];
        }

        return $items;
    }

    /**
     * Returns the saved records as a `list<array<string, mixed>>` ready to feed `Queue::search()`. Defends against
     * stray non-array entries / non-string keys that might survive a malformed `$panel->data` payload; both shapes the
     * search-model contract refuses to accept.
     *
     * @return list<array<string, mixed>>
     */
    private function arrayRecords(): array
    {
        if (!is_array($this->data) || !is_array($this->data['records'] ?? null)) {
            return [];
        }

        $out = [];

        foreach ($this->data['records'] as $record) {
            if (!is_array($record)) {
                continue;
            }

            $stringKeyed = [];

            foreach ($record as $key => $value) {
                if (is_string($key)) {
                    $stringKeyed[$key] = $value;
                }
            }

            $out[] = $stringKeyed;
        }

        return $out;
    }

    /**
     * Releases the per-job `$execStarts` slot on long-running workers so the map cannot grow indefinitely.
     */
    private function clearExecStart(Event $event): void
    {
        $job = $this->jobOf($event);

        if ($job !== null) {
            unset($this->execStarts[spl_object_id($job)]);
        }
    }

    private function componentIdOf(Event $event): string
    {
        $sender = $event->sender;

        if (!is_object($sender)) {
            return '';
        }

        $key = spl_object_id($sender);

        if (isset($this->componentIdCache[$key])) {
            return $this->componentIdCache[$key];
        }

        foreach (Yii::$app->getComponents(false) as $rawId => $component) {
            if ($component === $sender) {
                return $this->componentIdCache[$key] = (string) $rawId;
            }
        }

        return $this->componentIdCache[$key] = '';
    }

    /**
     * Tests whether a single `Yii::$app->components` entry references one of the known queue base classes. Accepts the
     *  three shapes Yii allows: a class-name string, a config array with a `class` key, or an already-instantiated
     * component object.
     */
    private static function componentMatchesQueueBase(mixed $config): bool
    {
        if (is_object($config)) {
            // The queue base class is abstract, so concrete components can only `is_subclass_of` it; they can never
            // literally `=== self::QUEUE_BASE_CLASS`.
            return is_subclass_of($config, self::QUEUE_BASE_CLASS);
        }

        $class = null;

        if (is_string($config)) {
            $class = $config;
        } elseif (is_array($config) && is_string($config['class'] ?? null)) {
            $class = $config['class'];
        }

        if ($class === null) {
            return false;
        }

        return $class === self::QUEUE_BASE_CLASS || is_subclass_of($class, self::QUEUE_BASE_CLASS);
    }

    private function errorMessageOf(mixed $error): string
    {
        return $error instanceof Throwable ? $error->getMessage() : '';
    }

    /**
     * Walks `Yii::$app->components` (without instantiating them) looking for any class that extends the queue base
     * class. The toolbar uses this to keep the Queue button visible on apps that DO configure queues even when no jobs
     * were pushed in the current request, mirroring the Database panel's behaviour.
     */
    private function hasQueueComponentConfigured(): bool
    {
        if (
            class_exists(self::QUEUE_BASE_CLASS, false) === false
            && interface_exists(self::QUEUE_BASE_CLASS, false) === false
        ) {
            // Pre-load the abstract base via `class_exists` (autoload-aware). When the queue package is not installed
            // at all, `is_subclass_of` returns `false` below; so the panel stays hidden.
            class_exists(self::QUEUE_BASE_CLASS);
        }

        // `getComponents(true)` returns ALL component definitions (including lazy ones that haven't been instantiated
        // yet); the alternative `false` form would only see components already touched in the request, and most apps'
        // queue components are lazy until a controller calls `push()`.
        foreach (Yii::$app->getComponents(true) as $config) {
            if (self::componentMatchesQueueBase($config)) {
                return true;
            }
        }

        return false;
    }

    private function jobOf(Event $event): object|null
    {
        $props = (array) $event;

        return is_object($props['job'] ?? null) ? $props['job'] : null;
    }

    /**
     * @return array{
     *   eventType: string,
     *   componentId: string,
     *   driverName: string,
     *   driverClass: string,
     *   isAsync: bool,
     *   jobClass: string,
     *   payloadFields: array<string, mixed>,
     *   time: float,
     *   jobId: string,
     *   ttr: int|null,
     *   delay: int|null,
     *   priority: int|null,
     *   attempt: int|null,
     *   duration: float|null,
     *   error: string,
     * }
     */
    private function makeRecord(string $eventType, Event $event): array
    {
        // Yii's queue events (`yii\queue\JobEvent` / `PushEvent` / `ExecEvent`) carry public properties such as `job`,
        // `id`, `ttr`, `delay`, `priority`, `attempt`, `error`. The base class `yii\base\Event` doesn't declare them,
        // so we cast to array — that exposes every public field as a string-keyed entry without any dynamic property
        // access PHPStan would flag.
        $props = (array) $event;

        $job = is_object($props['job'] ?? null) ? $props['job'] : null;

        $jobClass = $job === null ? '' : get_class($job);

        $sender = $event->sender;

        $driverClass = is_object($sender) ? get_class($sender) : '';

        [$driverName, $isAsync] = QueueDriverDetector::detect($driverClass);

        $duration = null;

        if ($job !== null && ($eventType === JobRecord::TYPE_EXEC || $eventType === JobRecord::TYPE_ERROR)) {
            $start = $this->execStarts[spl_object_id($job)] ?? null;

            if ($start !== null) {
                $duration = microtime(true) - $start;
            }
        }

        return [
            'eventType' => $eventType,
            'componentId' => $this->componentIdOf($event),
            'driverName' => $driverName,
            'driverClass' => $driverClass,
            'isAsync' => $isAsync,
            'jobClass' => $jobClass,
            'payloadFields' => $job === null ? [] : JobPayloadInspector::extract($job),
            'time' => microtime(true),
            'jobId' => $this->scalarToString($props['id'] ?? null),
            'ttr' => $this->valueToNullableInt($props['ttr'] ?? null),
            'delay' => $this->valueToNullableInt($props['delay'] ?? null),
            'priority' => $this->valueToNullableInt($props['priority'] ?? null),
            'attempt' => $this->valueToNullableInt($props['attempt'] ?? null),
            'duration' => $duration,
            'error' => $eventType === JobRecord::TYPE_ERROR ? $this->errorMessageOf($props['error'] ?? null) : '',
        ];
    }

    private function onAfterError(Event $event): void
    {
        $this->records[] = $this->makeRecord(JobRecord::TYPE_ERROR, $event);
        $this->clearExecStart($event);
    }

    private function onAfterExec(Event $event): void
    {
        $this->records[] = $this->makeRecord(JobRecord::TYPE_EXEC, $event);
        $this->clearExecStart($event);
    }

    private function onBeforeExec(Event $event): void
    {
        $job = $this->jobOf($event);

        if ($job !== null) {
            $this->execStarts[spl_object_id($job)] = microtime(true);
        }
    }

    private function onPush(Event $event): void
    {
        $this->records[] = $this->makeRecord(JobRecord::TYPE_PUSH, $event);
    }

    /**
     * Returns the captured queue records from whichever source is populated for the current request: the live
     * `$this->records` array (during the original request, while listeners are still firing) or the saved
     * `$this->data['records']` slice (during the toolbar AJAX replay or the detail-page render).
     *
     * @return list<mixed>
     */
    private function resolveRecords(): array
    {
        if ($this->records !== []) {
            return $this->records;
        }

        if (is_array($this->data) && is_array($this->data['records'] ?? null)) {
            return array_values($this->data['records']);
        }

        return [];
    }

    private function scalarToString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function valueToNullableInt(mixed $value): int|null
    {
        return is_int($value) ? $value : null;
    }
}
