<?php

declare(strict_types=1);

namespace yii\debug\panels\queue;

/**
 * Typed view-model for a single queue lifecycle event captured during the request (push, exec, or error).
 *
 * Mirrors the relevant subset of Yii Queue `JobEvent` after every value has been narrowed, so the consuming view
 * iterates and reads typed properties without inspecting the original event object.
 */
final readonly class JobRecord
{
    /**
     * @var list<string> Allowed event-type strings, in canonical order.
     *
     * The renderers and the search filter dropdown iterate this list.
     */
    public const array EVENT_TYPES = [self::TYPE_PUSH, self::TYPE_EXEC, self::TYPE_ERROR];

    /**
     * @var array<string, array{variant: string, label: string}> Maps each event type to the CSS modifier and human
     * label used by the status pill in both card and grid views.
     */
    public const array EVENT_VARIANTS = [
        self::TYPE_PUSH => ['variant' => 'queued', 'label' => 'Queued'],
        self::TYPE_EXEC => ['variant' => 'done', 'label' => 'Done'],
        self::TYPE_ERROR => ['variant' => 'failed', 'label' => 'Failed'],
    ];
    /**
     * Lifecycle phase value used when the job threw.
     */
    public const string TYPE_ERROR = 'error';
    /**
     * Lifecycle phase value used when the job finished successfully.
     */
    public const string TYPE_EXEC = 'exec';
    /**
     * Lifecycle phase value used when the job was enqueued.
     */
    public const string TYPE_PUSH = 'push';

    public function __construct(
        /**
         * Lifecycle phase: `'push'` (job enqueued), `'exec'` (job finished successfully) or `'error'` (job threw).
         */
        public string $eventType,
        /**
         * Identifier of the queue component that emitted the event (`'queue'`, `'queueEmail'`, ...). `''` when the
         * sender could not be matched against any registered component.
         */
        public string $componentId,
        /**
         * Friendly display name of the queue driver detected from the event sender's class name (`'Sync'`,
         * `'Database'`, `'Redis'`, `'AMQP'`, `'Beanstalk'`, `'Gearman'`, or a custom-detected fallback).
         */
        public string $driverName,
        /**
         * Fully qualified class name of the queue driver that emitted the event. `''` when the sender was not an
         * object (defensive default).
         */
        public string $driverClass,
        /**
         * `false` when the driver runs jobs in-process during the same request (sync), `true` when jobs run in a
         * separate worker (db, redis, amqp, beanstalk, gearman). The detail view uses this flag to show a hint
         * about exec events living in CLI debug snapshots.
         */
        public bool $isAsync,
        /**
         * Fully qualified class name of the job. `''` when the event carried no job (defensive default).
         */
        public string $jobClass,
        /**
         * @var array<string, mixed> Recursively normalised public-property tree of the job payload, captured at push
         * time. Scalars (string/int/float/bool/null) round-trip as-is; nested objects expand into nested arrays
         * with a synthetic `__class` key carrying their FQCN. Empty when the event carried no job.
         */
        public array $payloadFields,
        /**
         * Capture timestamp in seconds since the Unix epoch (microseconds preserved as the fractional part).
         */
        public float $time,
        /**
         * Driver-specific message id (the queue's internal handle), or `''` when the driver did not expose one.
         */
        public string $jobId,
        /**
         * Time-to-run override declared at push time, in seconds. `null` when the driver default applies.
         */
        public int|null $ttr,
        /**
         * Delay before execution declared at push time, in seconds. `null` when the driver default applies.
         */
        public int|null $delay,
        /**
         * Priority override declared at push time. `null` when the driver default applies.
         */
        public int|null $priority,
        /**
         * Current attempt number for `exec` / `error` events (`1` for the first run). `null` for `push` events.
         */
        public int|null $attempt,
        /**
         * Execution time in seconds for `exec` / `error` events. `null` for `push` events.
         */
        public float|null $duration,
        /**
         * Captured exception message for `error` events. `''` for `push` / `exec` events.
         */
        public string $error,
    ) {}
}
