<?php

declare(strict_types=1);

namespace yii\debug\panels\profile;

use PHPForge\Debug\Storage\{PanelSnapshot, Payload};
use Yii;
use yii\debug\helpers\Coerce;
use yii\debug\panels\MemorySample;

use function array_map;
use function count;
use function is_array;

/**
 * Canonical profiling snapshot holding the request metrics, the resolved profile blocks, and the memory samples that
 * feed the timeline chart.
 */
final readonly class ProfilingSnapshot implements PanelSnapshot
{
    /**
     * @param list<ProfileRow> $entries
     * @param list<MemorySample> $samples
     */
    public function __construct(
        public int $memory,
        public float $time,
        private array $entries,
        private array $samples,
    ) {}

    /**
     * Resolves the logger's begin/end pairs into typed blocks and collects the per-message memory samples.
     *
     * @param array<int|string, mixed> $messages Raw profile tuples in capture order.
     */
    public static function capture(int $memory, float $time, array $messages): self
    {
        $tuples = [];
        $samples = [];

        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $tuples[] = $message;

            $sampleTime = Coerce::floatOrNull($message[3] ?? null);
            $sampleMemory = Coerce::intOrNull($message[5] ?? null);

            if ($sampleTime !== null && $sampleMemory !== null) {
                $samples[] = new MemorySample($sampleTime * 1000, $sampleMemory);
            }
        }

        $entries = [];

        foreach (Yii::getLogger()->calculateTimings($tuples) as $timing) {
            $row = ProfileRow::fromTiming($timing, count($entries));

            if ($row !== null) {
                $entries[] = $row;
            }
        }

        return new self($memory, $time, $entries, $samples);
    }

    /**
     * @return list<ProfileRow> Resolved profile blocks in capture order.
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)->shape(['memory', 'time', 'entries', 'samples']);

        $entries = [];

        foreach ($payload->list('entries') as $index => $entry) {
            $entries[] = ProfileRow::fromArray($entry, "{$path}.entries[{$index}]");
        }

        $samples = [];

        foreach ($payload->list('samples') as $index => $sample) {
            $samplePayload = Payload::object($sample, "{$path}.samples[{$index}]")->shape(['time', 'memory']);
            $samples[] = new MemorySample($samplePayload->number('time'), $samplePayload->int('memory'));
        }

        return new self($payload->int('memory'), $payload->number('time'), $entries, $samples);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'memory' => $this->memory,
            'time' => $this->time,
            'entries' => array_map(static fn(ProfileRow $row): array => $row->jsonSerialize(), $this->entries),
            'samples' => array_map(
                static fn(MemorySample $sample): array => ['time' => $sample->time, 'memory' => $sample->memory],
                $this->samples,
            ),
        ];
    }

    /**
     * @return list<MemorySample> Memory readings recorded alongside each captured profile message.
     */
    public function samples(): array
    {
        return $this->samples;
    }
}
