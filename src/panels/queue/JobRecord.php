<?php

declare(strict_types=1);

namespace yii\debug\panels\queue;

use yii\debug\helpers\Coerce;
use yii\debug\storage\{HydrationException, PanelRow, Payload};

use function in_array;
use function is_array;
use function is_string;

/**
 * Typed view-model for a single queue lifecycle event captured during the request (push, exec, or error).
 *
 * Mirrors the relevant subset of Yii Queue `JobEvent` after every value has been narrowed, so the consuming view
 * iterates and reads typed properties without inspecting the original event object.
 */
final readonly class JobRecord implements PanelRow
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

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)
            ->shape(
                [
                    'eventType',
                    'componentId',
                    'driverName',
                    'driverClass',
                    'isAsync',
                    'jobClass',
                    'payloadFields',
                    'time',
                    'jobId',
                    'ttr',
                    'delay',
                    'priority',
                    'attempt',
                    'duration',
                    'error',
                ],
            );

        $eventType = $payload->string('eventType');

        if (!in_array($eventType, self::EVENT_TYPES, true)) {
            throw HydrationException::at("{$path}.eventType", 'a known queue event type');
        }

        return new self(
            eventType: $eventType,
            componentId: $payload->string('componentId'),
            driverName: $payload->string('driverName'),
            driverClass: $payload->string('driverClass'),
            isAsync: $payload->bool('isAsync'),
            jobClass: $payload->string('jobClass'),
            payloadFields: $payload->map('payloadFields'),
            time: $payload->number('time'),
            jobId: $payload->string('jobId'),
            ttr: $payload->nullableInt('ttr'),
            delay: $payload->nullableInt('delay'),
            priority: $payload->nullableInt('priority'),
            attempt: $payload->nullableInt('attempt'),
            duration: $payload->nullableNumber('duration'),
            error: $payload->string('error'),
        );
    }

    /**
     * Narrows one captured queue-event payload into a typed record.
     *
     * @param array<array-key, mixed> $row Captured payload.
     */
    public static function fromCapture(array $row): self
    {
        $eventType = $row['eventType'] ?? null;
        $payload = $row['payloadFields'] ?? null;

        return new self(
            eventType: is_string($eventType) && in_array($eventType, self::EVENT_TYPES, true)
                ? $eventType
                : self::TYPE_PUSH,
            componentId: Coerce::string($row['componentId'] ?? null),
            driverName: Coerce::string($row['driverName'] ?? null),
            driverClass: Coerce::string($row['driverClass'] ?? null),
            isAsync: ($row['isAsync'] ?? false) === true,
            jobClass: Coerce::string($row['jobClass'] ?? null),
            payloadFields: is_array($payload) ? Coerce::stringKeyedArray($payload) : [],
            time: Coerce::float($row['time'] ?? null),
            jobId: Coerce::string($row['jobId'] ?? null),
            ttr: Coerce::intOrNull($row['ttr'] ?? null),
            delay: Coerce::intOrNull($row['delay'] ?? null),
            priority: Coerce::intOrNull($row['priority'] ?? null),
            attempt: Coerce::intOrNull($row['attempt'] ?? null),
            duration: Coerce::floatOrNull($row['duration'] ?? null),
            error: Coerce::string($row['error'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'eventType' => $this->eventType,
            'componentId' => $this->componentId,
            'driverName' => $this->driverName,
            'driverClass' => $this->driverClass,
            'isAsync' => $this->isAsync,
            'jobClass' => $this->jobClass,
            'payloadFields' => $this->payloadFields,
            'time' => $this->time,
            'jobId' => $this->jobId,
            'ttr' => $this->ttr,
            'delay' => $this->delay,
            'priority' => $this->priority,
            'attempt' => $this->attempt,
            'duration' => $this->duration,
            'error' => $this->error,
        ];
    }
}
