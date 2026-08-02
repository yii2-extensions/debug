<?php

declare(strict_types=1);

namespace yii\debug\storage;

use JsonSerializable;
use Throwable;

/**
 * Captures a panel failure without invalidating the remaining request snapshot.
 */
final readonly class PanelFailure implements JsonSerializable
{
    public const string CAPTURE = 'capture';
    public const string HYDRATE = 'hydrate';

    /**
     * @param 'capture'|'hydrate' $stage Lifecycle stage the panel failed in.
     */
    public function __construct(
        public string $stage,
        public ExceptionSnapshot $exception,
    ) {}

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)->shape(['stage', 'exception']);
        $stage = $payload->string('stage');

        if ($stage !== self::CAPTURE && $stage !== self::HYDRATE) {
            throw HydrationException::at("{$path}.stage", 'capture or hydrate');
        }

        return new self(
            $stage,
            ExceptionSnapshot::fromArray($payload->raw('exception'), "{$path}.exception"),
        );
    }

    /**
     * @param 'capture'|'hydrate' $stage Lifecycle stage the panel failed in.
     */
    public static function fromThrowable(string $stage, Throwable $throwable): self
    {
        return new self($stage, ExceptionSnapshot::fromThrowable($throwable));
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'stage' => $this->stage,
            'exception' => $this->exception->jsonSerialize(),
        ];
    }
}
