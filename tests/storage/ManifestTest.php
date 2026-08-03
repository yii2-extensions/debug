<?php

declare(strict_types=1);

namespace yii\debug\tests\storage;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use yii\debug\storage\{DebugSnapshot, HydrationException, Manifest, RequestSummary};

/**
 * Unit tests for {@see Manifest} covering the versioned index and the tag/key consistency guard.
 *
 * @since 0.2
 */
#[Group('storage')]
final class ManifestTest extends TestCase
{
    public function testRoundTripsEntriesKeyedByTag(): void
    {
        $manifest = new Manifest(['tag-1' => self::summary('tag-1')]);

        $restored = Manifest::fromArray($manifest->jsonSerialize());

        self::assertSame(
            ['tag-1'],
            array_keys($restored->entries),
            'Entries must stay keyed by tag.',
        );
    }

    public function testThrowHydrationExceptionWhenAnEntryTagDoesNotMatchItsKey(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '\$.entries.tag-1.tag'",
        );

        Manifest::fromArray(
            [
                'version' => DebugSnapshot::VERSION,
                'entries' => ['tag-1' => self::summary('other-tag')->jsonSerialize()],
            ],
        );
    }

    public function testThrowHydrationExceptionWhenTheStorageVersionDoesNotMatch(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '\$.version': expected storage version " . DebugSnapshot::VERSION . '.',
        );

        Manifest::fromArray(['version' => DebugSnapshot::VERSION - 1, 'entries' => []]);
    }

    private static function summary(string $tag): RequestSummary
    {
        return new RequestSummary(
            tag: $tag,
            url: 'https://example.test/',
            ajax: false,
            method: 'GET',
            ip: '127.0.0.1',
            time: 1_700_000_000.0,
            statusCode: 200,
            sqlCount: 0,
            excessiveCallersCount: 0,
            mailCount: 0,
            mailFiles: [],
            processingTime: null,
            peakMemory: null,
        );
    }
}
