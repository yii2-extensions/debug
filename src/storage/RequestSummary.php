<?php

declare(strict_types=1);

namespace yii\debug\storage;

use JsonSerializable;

use function is_string;

/**
 * Canonical metadata for one captured request and its manifest row.
 */
final readonly class RequestSummary implements JsonSerializable
{
    /**
     * @param list<string> $mailFiles
     */
    public function __construct(
        public string $tag,
        public string $url,
        public bool $ajax,
        public string $method,
        public string $ip,
        public float $time,
        public int $statusCode,
        public int $sqlCount,
        public int $excessiveCallersCount,
        public int $mailCount,
        public array $mailFiles,
        public float|null $processingTime,
        public int|null $peakMemory,
    ) {}

    public static function fromArray(mixed $data, string $path = '$.summary'): self
    {
        $payload = Payload::object($data, $path)->shape([
            'tag',
            'url',
            'ajax',
            'method',
            'ip',
            'time',
            'statusCode',
            'sqlCount',
            'excessiveCallersCount',
            'mailCount',
            'mailFiles',
            'processingTime',
            'peakMemory',
        ]);
        $mailFiles = [];

        foreach ($payload->list('mailFiles') as $index => $file) {
            if (!is_string($file)) {
                throw HydrationException::at("{$path}.mailFiles[{$index}]", 'a string');
            }

            $mailFiles[] = $file;
        }

        return new self(
            tag: $payload->string('tag'),
            url: $payload->string('url'),
            ajax: $payload->bool('ajax'),
            method: $payload->string('method'),
            ip: $payload->string('ip'),
            time: $payload->number('time'),
            statusCode: $payload->int('statusCode'),
            sqlCount: $payload->int('sqlCount'),
            excessiveCallersCount: $payload->int('excessiveCallersCount'),
            mailCount: $payload->int('mailCount'),
            mailFiles: $mailFiles,
            processingTime: $payload->nullableNumber('processingTime'),
            peakMemory: $payload->nullableInt('peakMemory'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'tag' => $this->tag,
            'url' => $this->url,
            'ajax' => $this->ajax,
            'method' => $this->method,
            'ip' => $this->ip,
            'time' => $this->time,
            'statusCode' => $this->statusCode,
            'sqlCount' => $this->sqlCount,
            'excessiveCallersCount' => $this->excessiveCallersCount,
            'mailCount' => $this->mailCount,
            'mailFiles' => $this->mailFiles,
            'processingTime' => $this->processingTime,
            'peakMemory' => $this->peakMemory,
        ];
    }

    public function withProfiling(float $processingTime, int $peakMemory): self
    {
        return new self(
            tag: $this->tag,
            url: $this->url,
            ajax: $this->ajax,
            method: $this->method,
            ip: $this->ip,
            time: $this->time,
            statusCode: $this->statusCode,
            sqlCount: $this->sqlCount,
            excessiveCallersCount: $this->excessiveCallersCount,
            mailCount: $this->mailCount,
            mailFiles: $this->mailFiles,
            processingTime: $processingTime,
            peakMemory: $peakMemory,
        );
    }
}
