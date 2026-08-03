<?php

declare(strict_types=1);

namespace yii\debug\tests\db;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\db\QueryRow;
use yii\debug\storage\HydrationException;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see QueryRow} covering the capture-time narrowing of logger timings and the strict JSON hydration
 * that restores them without coercion.
 *
 * @since 0.2
 */
#[Group('panel')]
#[Group('db')]
final class QueryRowTest extends TestCase
{
    public function testFromArrayClampsDuplicateToMinimumOfOne(): void
    {
        $row = QueryRow::fromArray(self::payload(['duplicate' => 0]), '$.panels.db.entries[0]');

        self::assertSame(1, $row->duplicate, 'A duplicate count below one must clamp to `1`.');
    }

    public function testFromArrayRoundTripsEveryField(): void
    {
        $row = new QueryRow(
            type: 'SELECT',
            query: 'SELECT * FROM t',
            duration: 5.0,
            trace: [['file' => '/app/index.php', 'line' => 12]],
            traceHash: 'abc123',
            timestamp: 1_700_000_000_000.0,
            seq: 3,
            duplicate: 2,
            rows: 42,
        );

        self::assertEquals(
            $row,
            QueryRow::fromArray($row->jsonSerialize(), '$.panels.db.entries[0]'),
            'Round-trip must preserve every field.',
        );
    }

    public function testFromTimingClampsDuplicateToMinimumOfOne(): void
    {
        $row = QueryRow::fromTiming(
            [
                'info' => 'SELECT 1',
                'category' => 'yii\\db\\Command::query',
                'timestamp' => 1.0,
                'trace' => [],
                'level' => 0,
                'duration' => 0.001,
                'memory' => 0,
                'memoryDiff' => 0,
                'traceHash' => 'hash',
            ],
            'SELECT',
            0,
            0,
            null,
        );

        self::assertSame(
            1,
            $row->duplicate,
            "A capture-time duplicate count below one must clamp to '1'.",
        );
    }

    public function testFromTimingScalesToMillisecondsAndKeepsCallerValues(): void
    {
        $row = QueryRow::fromTiming(
            [
                'info' => 'SELECT 1',
                'category' => 'yii\\db\\Command::query',
                'timestamp' => 2.5,
                'trace' => [['file' => 'a.php']],
                'level' => 0,
                'duration' => 0.005,
                'memory' => 0,
                'memoryDiff' => 0,
                'traceHash' => 'hash',
            ],
            'SELECT',
            7,
            3,
            42,
        );

        self::assertSame('SELECT', $row->type, 'The verb is supplied by the caller.');
        self::assertSame('SELECT 1', $row->query, 'The statement comes from the timing token.');
        self::assertEqualsWithDelta(5.0, $row->duration, 1e-9, 'Duration must be scaled to milliseconds.');
        self::assertEqualsWithDelta(2_500.0, $row->timestamp, 1e-9, 'Timestamp must be scaled to milliseconds.');
        self::assertSame('hash', $row->traceHash, 'Trace hash must round-trip.');
        self::assertSame(7, $row->seq, 'Sequence index is supplied by the caller.');
        self::assertSame(3, $row->duplicate, 'Duplicate count is supplied by the caller.');
        self::assertSame(42, $row->rows, 'Row count is supplied by the caller.');
    }

    public function testThrowHydrationExceptionWhenDurationIsANumericString(): void
    {
        $this->expectException(HydrationException::class);

        QueryRow::fromArray(self::payload(['duration' => '5.0']), '$.panels.db.entries[0]');
    }

    public function testThrowHydrationExceptionWhenTraceIsNotAListOfObjects(): void
    {
        $this->expectException(HydrationException::class);

        QueryRow::fromArray(self::payload(['trace' => ['not-a-frame']]), '$.panels.db.entries[0]');
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function payload(array $overrides = []): array
    {
        return [
            'type' => 'SELECT',
            'query' => 'SELECT 1',
            'duration' => 1.0,
            'trace' => [],
            'traceHash' => 'hash',
            'timestamp' => 1.0,
            'seq' => 0,
            'duplicate' => 1,
            'rows' => null,
            ...$overrides,
        ];
    }
}
