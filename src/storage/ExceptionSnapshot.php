<?php

declare(strict_types=1);

namespace yii\debug\storage;

use JsonSerializable;
use Stringable;
use Throwable;
use yii\debug\helpers\Fqcn;

use function array_map;
use function is_array;
use function is_int;
use function is_string;

/**
 * JSON-safe, non-executable representation of a captured throwable.
 */
final readonly class ExceptionSnapshot implements JsonSerializable, Stringable
{
    /**
     * Frame arguments stay in their tagged form so a snapshot read back from disk re-serializes byte for byte;
     * {@see getTrace()} projects them to display values on demand.
     *
     * @param list<array{
     *   namespace: string,
     *   short_class: string,
     *   class: string,
     *   type: string,
     *   function: string|null,
     *   file: string|null,
     *   line: int|null,
     *   args: DebugArray,
     * }> $trace
     */
    public function __construct(
        private string $class,
        private string $message,
        private int|string $code,
        private string $file,
        private int $line,
        private array $trace,
        private string $toString,
        private self|null $previous,
    ) {}

    public function __toString(): string
    {
        return $this->toString;
    }

    public static function fromArray(mixed $data, string $path = '$.exception'): self
    {
        $payload = Payload::object($data, $path)->shape([
            'class',
            'message',
            'code',
            'file',
            'line',
            'trace',
            'toString',
            'previous',
        ]);
        $rawCode = $payload->raw('code');

        if (!is_int($rawCode) && !is_string($rawCode)) {
            throw HydrationException::at("{$path}.code", 'an integer or string');
        }

        $trace = [];

        foreach ($payload->list('trace') as $index => $rawFrame) {
            $framePath = "{$path}.trace[{$index}]";
            $frame = Payload::object($rawFrame, $framePath)->shape([
                'namespace',
                'short_class',
                'class',
                'type',
                'function',
                'file',
                'line',
                'args',
            ]);
            $trace[] = [
                'namespace' => $frame->string('namespace'),
                'short_class' => $frame->string('short_class'),
                'class' => $frame->string('class'),
                'type' => $frame->string('type'),
                'function' => $frame->nullableString('function'),
                'file' => $frame->nullableString('file'),
                'line' => $frame->nullableInt('line'),
                'args' => DebugArray::fromArray($frame->raw('args'), "{$framePath}.args"),
            ];
        }

        $previous = $payload->raw('previous');

        return new self(
            class: $payload->string('class'),
            message: $payload->string('message'),
            code: $rawCode,
            file: $payload->string('file'),
            line: $payload->int('line'),
            trace: $trace,
            toString: $payload->string('toString'),
            previous: $previous === null ? null : self::fromArray($previous, "{$path}.previous"),
        );
    }

    public static function fromThrowable(Throwable $throwable): self
    {
        $trace = [];

        foreach ($throwable->getTrace() as $entry) {
            $class = is_string($entry['class'] ?? null) ? $entry['class'] : '';
            $args = is_array($entry['args'] ?? null) ? $entry['args'] : [];

            $trace[] = [
                'namespace' => Fqcn::namespacePart($class),
                'short_class' => Fqcn::shortName($class),
                'class' => $class,
                'type' => is_string($entry['type'] ?? null) ? $entry['type'] : '',
                'function' => $entry['function'],
                'file' => is_string($entry['file'] ?? null) ? $entry['file'] : null,
                'line' => is_int($entry['line'] ?? null) ? $entry['line'] : null,
                'args' => DebugArray::capture($args),
            ];
        }

        $code = $throwable->getCode();

        return new self(
            class: $throwable::class,
            message: $throwable->getMessage(),
            code: $code,
            file: $throwable->getFile(),
            line: $throwable->getLine(),
            trace: $trace,
            toString: (string) $throwable,
            previous: $throwable->getPrevious() !== null ? self::fromThrowable($throwable->getPrevious()) : null,
        );
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getCode(): int|string
    {
        return $this->code;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getPrevious(): self|null
    {
        return $this->previous;
    }

    /**
     * Returns the trace frames with their arguments projected to plain display values.
     *
     * @return list<array<string, mixed>>
     */
    public function getTrace(): array
    {
        return array_map(
            static fn(array $frame): array => [...$frame, 'args' => $frame['args']->values()],
            $this->trace,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'class' => $this->class,
            'message' => $this->message,
            'code' => $this->code,
            'file' => $this->file,
            'line' => $this->line,
            'trace' => array_map(
                static fn(array $frame): array => [...$frame, 'args' => $frame['args']->jsonSerialize()],
                $this->trace,
            ),
            'toString' => $this->toString,
            'previous' => $this->previous?->jsonSerialize(),
        ];
    }
}
