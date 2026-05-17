<?php

declare(strict_types=1);

namespace yii\debug\tests\queue;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\queue\QueueDriverDetector;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see QueueDriverDetector} covering the per-driver FQCN → `[label, isAsync]` mapping, including the
 * sync short-circuit, the `__` snake-case title-cased fallback for unknown drivers, the empty-FQCN fallback, the
 * single-segment FQCN fallback, and the per-FQCN cache.
 */
#[Group('queue')]
final class QueueDriverDetectorTest extends TestCase
{
    public function testDetectCachesResultByFqcnAcrossInvocations(): void
    {
        $this->setInaccessibleStaticProperty(
            QueueDriverDetector::class,
            'cache',
            [],
        );

        $first = QueueDriverDetector::detect('yii\\queue\\redis\\Queue');
        $second = QueueDriverDetector::detect('yii\\queue\\redis\\Queue');

        self::assertSame(
            $first,
            $second,
            'Repeated lookups for the same FQCN must return the cached tuple verbatim.',
        );
    }

    public function testDetectClassifiesKnownAsyncDrivers(): void
    {
        $this->setInaccessibleStaticProperty(
            QueueDriverDetector::class,
            'cache',
            [],
        );

        self::assertSame(
            ['Database', true],
            QueueDriverDetector::detect('yii\\queue\\db\\Queue'),
            "Database driver must use the 'Database' display label and 'async=true'.",
        );
        self::assertSame(
            ['Redis', true],
            QueueDriverDetector::detect('yii\\queue\\redis\\Queue'),
            'Redis driver must use the Redis display label.',
        );
        self::assertSame(
            ['AMQP', true],
            QueueDriverDetector::detect('yii\\queue\\amqp\\Queue'),
            'AMQP driver must use the AMQP display label.',
        );
        self::assertSame(
            ['AMQP', true],
            QueueDriverDetector::detect('yii\\queue\\amqp_interop\\Queue'),
            "'amqp_interop' driver must alias back to the AMQP display label.",
        );
    }
    public function testDetectClassifiesSyncDriverAsRunInProcess(): void
    {
        // Reset cache via reflection so prior runs do not mask the title-case path.
        $this->setInaccessibleStaticProperty(
            QueueDriverDetector::class,
            'cache',
            [],
        );

        self::assertSame(
            ['Sync', false],
            QueueDriverDetector::detect('yii\\queue\\sync\\Queue'),
            'Sync driver must report `isAsync = false`.',
        );
    }

    public function testDetectFallsBackToLowercasedFqcnForSingleSegmentClass(): void
    {
        $this->setInaccessibleStaticProperty(
            QueueDriverDetector::class,
            'cache',
            [],
        );

        self::assertSame(
            ['Customqueue', true],
            QueueDriverDetector::detect('CustomQueue'),
            'Single-segment FQCN must title-case the lowercased class itself as the driver label.',
        );
    }

    public function testDetectReturnsUnknownForEmptyFqcn(): void
    {
        $this->setInaccessibleStaticProperty(
            QueueDriverDetector::class,
            'cache',
            [],
        );

        self::assertSame(
            ['Unknown', true],
            QueueDriverDetector::detect(''),
            "Empty FQCN must surface as the 'Unknown' label with 'async=true'.",
        );
    }

    public function testDetectReturnsUnknownWhenExtractedTokenIsEmpty(): void
    {
        $this->setInaccessibleStaticProperty(
            QueueDriverDetector::class,
            'cache',
            [],
        );

        self::assertSame(
            ['Unknown', true],
            QueueDriverDetector::detect('\\Foo'),
            "Leading-backslash FQCNs produce an empty driver token; 'titleCase()' must fall back to 'Unknown'.",
        );
    }

    public function testDetectTitleCasesUnknownDriverTokensWithUnderscoreSeparator(): void
    {
        $this->setInaccessibleStaticProperty(
            QueueDriverDetector::class,
            'cache',
            [],
        );

        self::assertSame(
            ['MyCustom', true],
            QueueDriverDetector::detect('app\\queue\\my_custom\\Queue'),
            "Unknown snake_case driver tokens must title-case into 'MyCustom'.",
        );
    }
}
