<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use Closure;
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Helper\{Coerce, SensitiveDataRedactor};
use PHPForge\Debug\Panel\Queue\{JobPayloadInspector, JobRecord, QueueDriverDetector, QueueSnapshot};
use Throwable;
use Yii;
use yii\base\Event;

use function array_unique;
use function array_values;
use function is_int;
use function is_object;
use function is_scalar;
use function microtime;
use function spl_object_id;

/**
 * Captures every queue lifecycle event (`afterPush`, `afterExec`, `afterError`) emitted by any class extending
 * `yii\queue\Queue` from `yiisoft/yii2-queue`.
 *
 * Listeners are attached at {@see startup()} via `Event::on()` using the queue base class FQCN as a string, so the
 * collector registers cleanly even when the `yiisoft/yii2-queue` package is not installed, and detached again at
 * {@see shutdown()} so a reused worker process starts clean.
 *
 * Usage example:
 *
 * ```php
 * $snapshot = (new \yii\debug\collectors\QueueCollector())->capture();
 * ```
 */
class QueueCollector extends Collector
{
    /**
     * Queue base class whose events are listened on; the abstract base `yii\queue\Queue` from `yiisoft/yii2-queue`
     * that every concrete driver extends.
     */
    private const string QUEUE_BASE_CLASS = 'yii\queue\Queue';
    /**
     * @var list<string> Public job-property names whose values are replaced before snapshot persistence.
     */
    public array $redactedProperties = [
        'accessToken',
        'apiKey',
        'authorization',
        'password',
        'refreshToken',
        'secret',
        'token',
    ];

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
     * @var array<string, Closure(Event): void> Active listeners keyed by event name, kept so {@see stop()} can detach
     * them.
     */
    private array $listeners = [];

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
     * Snapshots the captured queue records.
     *
     * @return QueueSnapshot|null Captured queue payload; `null` when the collector never started.
     */
    public function capture(): QueueSnapshot|null
    {
        if (!$this->isStarted()) {
            return null;
        }

        return QueueSnapshot::capture($this->records);
    }

    /**
     * Returns the stable ID pairing this collector with the Queue panel.
     *
     * Usage example:
     *
     * ```php
     * $id = (new \yii\debug\collectors\QueueCollector())->id();
     * ```
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'queue';
    }

    /**
     * Subscribes to the four queue lifecycle events (`afterPush`, `beforeExec`, `afterExec`, `afterError`).
     */
    protected function start(): void
    {
        $this->componentIdCache = [];
        $this->execStarts = [];
        $this->records = [];
        $this->listeners = [
            'afterPush' => $this->onPush(...),
            'beforeExec' => $this->onBeforeExec(...),
            'afterExec' => $this->onAfterExec(...),
            'afterError' => $this->onAfterError(...),
        ];

        foreach ($this->listeners as $name => $listener) {
            Event::on(self::QUEUE_BASE_CLASS, $name, $listener);
        }
    }

    /**
     * Detaches the queue listeners and clears the accumulated state, so a reused worker process starts clean.
     */
    protected function stop(): void
    {
        foreach ($this->listeners as $name => $listener) {
            Event::off(self::QUEUE_BASE_CLASS, $name, $listener);
        }

        $this->componentIdCache = [];
        $this->execStarts = [];
        $this->listeners = [];
        $this->records = [];
    }

    /**
     * Returns the global capture policy plus the backward-compatible queue-specific exact-key list.
     */
    private function capturePolicy(): CapturePolicy
    {
        if ($this->module !== null) {
            return $this->module->createCapturePolicy($this->redactedProperties);
        }

        return new CapturePolicy(
            sensitiveKeys: array_values(
                array_unique([...SensitiveDataRedactor::DEFAULT_KEYS, ...$this->redactedProperties]),
            ),
            sensitiveKeyPatterns: SensitiveDataRedactor::DEFAULT_PATTERNS,
        );
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
     * Returns the exception message when `$error` is a {@see Throwable}, or `''` otherwise.
     */
    private function errorMessageOf(mixed $error): string
    {
        return $error instanceof Throwable ? $error->getMessage() : '';
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

        $jobClass = $job === null ? '' : $job::class;

        $sender = $event->sender;

        $driverClass = is_object($sender) ? $sender::class : '';

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
            'payloadFields' => $job === null
                ? []
                : Coerce::stringKeyedArray(
                    $this->capturePolicy()->redact(JobPayloadInspector::extract($job)),
                ),
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
