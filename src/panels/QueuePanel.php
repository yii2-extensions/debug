<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Throwable;
use Yii;
use yii\base\Event;
use yii\debug\actions\queue\JobAction;
use yii\debug\models\search\QueueSearch;
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
 * Captures every queue lifecycle event (`afterPush`, `afterExec`, `afterError`) emitted by any class extending
 * `yii\queue\Queue` from `yiisoft/yii2-queue`.
 *
 * Listeners are attached via `Event::on()` using the queue base class FQCN as a string, so the panel registers cleanly
 * even when the `yiisoft/yii2-queue` package is not installed; in that case the empty-state view is shown.
 */
class QueuePanel extends Panel
{
    /**
     * Queue base class whose events are listened on; the abstract base `yii\queue\Queue` from `yiisoft/yii2-queue`
     * that every concrete driver extends.
     */
    private const string QUEUE_BASE_CLASS = 'yii\queue\Queue';

    /**
     * Map of `spl_object_id($queueComponent) => component-id` populated lazily inside event listeners so each event's
     * sender can be matched back to its registered name in `Yii::$app->components`.
     *
     * @var array<int, string>
     */
    private array $componentIdCache = [];

    /**
     * @var array<int, float> Track exec start times keyed by `spl_object_id($job)` so the matching `afterExec` /
     * `afterError` event can compute the elapsed duration without depending on the queue driver.
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
     * }> Queue lifecycle events captured for the current request, in fire order.
     */
    private array $records = [];

    /**
     * Renders the detail view with the queue cards list.
     */
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

    /**
     * Returns the panel display name.
     */
    public function getName(): string
    {
        return 'Queue';
    }

    /**
     * Returns the toolbar icon name.
     */
    public function getToolbarIcon(): string
    {
        return 'queue';
    }

    /**
     * Registers the `queue-job` action and subscribes to the four queue lifecycle events (`afterPush`, `beforeExec`,
     * `afterExec`, `afterError`).
     */
    public function init(): void
    {
        parent::init();

        $this->actions['queue-job'] = [
            'class' => JobAction::class,
            'panel' => $this,
        ];

        Event::on(self::QUEUE_BASE_CLASS, 'afterPush', $this->onPush(...));
        Event::on(self::QUEUE_BASE_CLASS, 'beforeExec', $this->onBeforeExec(...));
        Event::on(self::QUEUE_BASE_CLASS, 'afterExec', $this->onAfterExec(...));
        Event::on(self::QUEUE_BASE_CLASS, 'afterError', $this->onAfterError(...));
    }

    /**
     * Always returns `true`: the panel is harmless without `yiisoft/yii2-queue` installed (the empty-state view kicks
     * in instead).
     */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * Snapshots the captured queue records.
     *
     * @return array{records: list<array<string, mixed>>} Captured payload consumed by {@see arrayRecords()} on
     * read-back.
     */
    public function save(): array
    {
        return ['records' => $this->records];
    }

    /**
     * Builds the toolbar items.
     *
     * Reads from {@see resolveRecords()} so the live request render and the toolbar AJAX replay (which only sees the
     * saved snapshot in `$this->data['records']`) report the same numbers. Hides the button entirely on apps that
     * don't configure any queue component, and surfaces an `Errors` chip in `danger` when at least one error event was
     * captured.
     *
     * @return array<int, array<string, mixed>>|null Toolbar items, or `null` when no queue component is configured and
     * no events were captured.
     */
    protected function getToolbarItems(): array|null
    {
        $records = $this->resolveRecords();

        if ($records === [] && $this->hasQueueComponentConfigured() === false) {
            return null;
        }

        $errors = 0;

        foreach ($records as $record) {
            if (is_array($record) && ($record['eventType'] ?? null) === JobRecord::TYPE_ERROR) {
                $errors++;
            }
        }

        $items = [['value' => count($records)]];

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
     * Returns the saved records ready to feed `Queue::search()`.
     *
     * Defends against stray non-array entries and non-string keys that might survive a malformed `$panel->data` payload
     * (both shapes the search-model contract refuses to accept).
     *
     * @return list<array<string, mixed>> Sanitized records in original order.
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

    /**
     * Resolves the registered component id for the queue object that emitted `$event`, caching the lookup per object.
     *
     * Returns `''` when the sender is not an object or cannot be matched against any registered component.
     */
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
     * Tests whether a single `Yii::$app->components` entry references one of the known queue base classes.
     *
     * Accepts the three shapes Yii allows: a class-name string, a config array with a `class` key, or an
     * already-instantiated component object. For object inputs only `is_subclass_of` matches, since the queue base
     * class is abstract.
     */
    private static function componentMatchesQueueBase(mixed $config): bool
    {
        if (is_object($config)) {
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

    /**
     * Returns the exception message when `$error` is a {@see Throwable}, or `''` otherwise.
     */
    private function errorMessageOf(mixed $error): string
    {
        return $error instanceof Throwable ? $error->getMessage() : '';
    }

    /**
     * Returns whether the application registers at least one queue component.
     *
     * Walks `Yii::$app->components` without instantiating lazy components so the panel can keep the Queue button
     * visible on apps that DO configure queues, even when no jobs were pushed in the current request (mirroring the
     * Database panel's behavior). Pre-loads the abstract base via `class_exists` so the `is_subclass_of` check works
     * when the queue package was not loaded yet; when the package is missing entirely, the check returns `false` and
     * the panel stays hidden.
     */
    private function hasQueueComponentConfigured(): bool
    {
        if (
            class_exists(self::QUEUE_BASE_CLASS, false) === false
            && interface_exists(self::QUEUE_BASE_CLASS, false) === false
        ) {
            class_exists(self::QUEUE_BASE_CLASS);
        }

        foreach (Yii::$app->getComponents(true) as $config) {
            if (self::componentMatchesQueueBase($config)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extracts the `job` public property from an event, returning `null` when the property is missing or not an
     * object.
     */
    private function jobOf(Event $event): object|null
    {
        $props = (array) $event;

        return is_object($props['job'] ?? null) ? $props['job'] : null;
    }

    /**
     * Builds one typed record from a queue lifecycle event.
     *
     * Reads the event's public properties (`job`, `id`, `ttr`, `delay`, `priority`, `attempt`, `error`) by casting it
     * to an array; the base class {@see Event} doesn't declare those fields, so the cast is the simplest way to
     * expose them without dynamic property access. Computes the exec duration by pairing the matching `beforeExec`
     * timestamp captured in {@see $execStarts}.
     *
     * @param string $eventType One of `JobRecord::TYPE_*`.
     * @param Event $event Queue lifecycle event.
     *
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
     * } Typed record ready for {@see $records}.
     */
    private function makeRecord(string $eventType, Event $event): array
    {
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

    /**
     * Records an `error` event and releases the matching exec-start slot.
     */
    private function onAfterError(Event $event): void
    {
        $this->records[] = $this->makeRecord(JobRecord::TYPE_ERROR, $event);
        $this->clearExecStart($event);
    }

    /**
     * Records an `exec` event and releases the matching exec-start slot.
     */
    private function onAfterExec(Event $event): void
    {
        $this->records[] = $this->makeRecord(JobRecord::TYPE_EXEC, $event);
        $this->clearExecStart($event);
    }

    /**
     * Stamps the job's exec start timestamp in {@see $execStarts}, so `afterExec` / `afterError` can compute the
     * duration.
     */
    private function onBeforeExec(Event $event): void
    {
        $job = $this->jobOf($event);

        if ($job !== null) {
            $this->execStarts[spl_object_id($job)] = microtime(true);
        }
    }

    /**
     * Records a `push` event.
     */
    private function onPush(Event $event): void
    {
        $this->records[] = $this->makeRecord(JobRecord::TYPE_PUSH, $event);
    }

    /**
     * Returns the captured records from whichever source is populated for the current request.
     *
     * During the original request (while listeners are still firing) returns {@see $records}; during the toolbar AJAX
     * replay or the detail-page render returns the `records` slice of `$this->data`.
     *
     * @return list<mixed> Captured records in capture order.
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

    /**
     * Stringifies the value when it is scalar, falling back to `''` otherwise.
     */
    private function scalarToString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Returns the value when it is already an int, falling back to `null` otherwise.
     */
    private function valueToNullableInt(mixed $value): int|null
    {
        return is_int($value) ? $value : null;
    }
}
