<?php

declare(strict_types=1);

namespace yii\debug\tests\queue;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\queue\JobPayloadInspector;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see JobPayloadInspector} covering the public-property tree extraction: scalar pass-through,
 * nested array recursion, nested-object expansion with `__class`, the `__truncated` depth fence, resource
 * stringification, and the unreadable/unsupported fallbacks.
 */
#[Group('queue')]
final class JobPayloadInspectorTest extends TestCase
{
    public function testExtractCatchesUnreadablePropertyExceptions(): void
    {
        $job = new class {
            public int $uninitialized; // @phpstan-ignore property.uninitialized
        };

        $fields = JobPayloadInspector::extract($job);

        self::assertSame(
            '(unreadable)',
            $fields['uninitialized'] ?? null,
            "Uninitialized typed properties must surface as '(unreadable)'.",
        );
    }

    public function testExtractCollapsesArrayBeyondDepthLimit(): void
    {
        // Build a deeply nested array exceeding `MAX_DEPTH = 6`.
        $deep = ['inner' => 'leaf'];

        for ($i = 0; $i < 10; $i++) {
            $deep = ['inner' => $deep];
        }

        $job = new class ($deep) {
            /**
             * @param array<array-key, mixed> $nested
             */
            public function __construct(public array $nested) {}
        };

        $fields = JobPayloadInspector::extract($job);

        $cursor = $fields['nested'] ?? null;
        $truncationFound = false;

        for ($i = 0; $i < 12 && is_array($cursor); $i++) {
            if (isset($cursor['__truncated'])) {
                $truncationFound = true;
                break;
            }

            $cursor = $cursor['inner'] ?? null;
        }

        self::assertTrue(
            $truncationFound,
            "Arrays nested beyond 'MAX_DEPTH' must collapse to the '__truncated' marker.",
        );
    }

    public function testExtractCollapsesNestedObjectBeyondDepthLimit(): void
    {
        $root = new class {
            public mixed $child = null;
        };

        $cursor = $root;

        // Build a chain of nested objects deeper than `MAX_DEPTH = 6`.
        for ($i = 0; $i < 10; $i++) {
            $next = new class {
                public mixed $child = null;
            };

            $cursor->child = $next;
            $cursor = $next;
        }

        $cursor->child = 'leaf';

        $fields = JobPayloadInspector::extract($root);

        $cursor = $fields['child'] ?? null;
        $truncationFound = false;

        for ($i = 0; $i < 12 && is_array($cursor); $i++) {
            if (isset($cursor['__truncated'])) {
                $truncationFound = true;
                self::assertArrayHasKey(
                    '__class',
                    $cursor,
                    "Truncated objects must still surface their '__class' marker.",
                );

                break;
            }

            $cursor = $cursor['child'] ?? null;
        }

        self::assertTrue(
            $truncationFound,
            "Objects nested beyond 'MAX_DEPTH' must collapse to the '__class' + '__truncated' marker pair.",
        );
    }

    public function testExtractExpandsNestedObjectWithClassMarker(): void
    {
        $inner = new class {
            public string $value = 'inner';
        };
        $job = new class ($inner) {
            public function __construct(public object $child) {}
        };

        $fields = JobPayloadInspector::extract($job);

        $child = $fields['child'] ?? null;

        self::assertIsArray(
            $child,
            'Nested object must surface as an array tree.',
        );
        self::assertArrayHasKey(
            '__class',
            $child,
            "Nested object expansion must carry the '__class' marker.",
        );
        self::assertSame(
            'inner',
            $child['value'] ?? null,
            'Nested public property must be expanded.',
        );
    }

    public function testExtractMarksClosedResourceAsUnsupported(): void
    {
        $handle = fopen('php://memory', 'rb');

        self::assertIsResource(
            $handle,
            "'fopen()' fixture must return a usable resource.",
        );

        fclose($handle);

        $job = new class ($handle) {
            public function __construct(public mixed $stream) {}
        };

        $fields = JobPayloadInspector::extract($job);

        self::assertSame(
            '(unsupported)',
            $fields['stream'] ?? null,
            "Closed resources lose their 'resource' type marker and must collapse to '(unsupported)'.",
        );
    }

    public function testExtractRecursesIntoNestedArraysPreservingKeys(): void
    {
        $job = new class {
            /**
             * @var array<string, mixed>
             */
            public array $payload = ['name' => 'Wilmer', 'tags' => ['urgent', 'email']];
        };

        $fields = JobPayloadInspector::extract($job);

        self::assertIsArray(
            $fields['payload'] ?? null,
            'Nested array must surface as an array.',
        );
        self::assertSame(
            'Wilmer',
            $fields['payload']['name'] ?? null,
            'String keys must be preserved.',
        );
        self::assertSame(
            ['urgent', 'email'],
            $fields['payload']['tags'] ?? null,
            'Inner lists must round-trip.',
        );
    }

    public function testExtractReturnsScalarPropertiesVerbatim(): void
    {
        $job = new class {
            public int $count = 42;
            public string $name = 'hello';
            public bool $flag = true;
            public float $ratio = 1.5;
            public mixed $blank = null;
        };

        $fields = JobPayloadInspector::extract($job);

        self::assertSame(
            42,
            $fields['count'] ?? null,
            'Integer scalar must round-trip.',
        );
        self::assertSame(
            'hello',
            $fields['name'] ?? null,
            'String scalar must round-trip.',
        );
        self::assertTrue(
            $fields['flag'] ?? null,
            'Boolean scalar must round-trip.',
        );
        self::assertSame(
            1.5,
            $fields['ratio'] ?? null,
            'Float scalar must round-trip.',
        );
        self::assertArrayHasKey(
            'blank',
            $fields,
            "'null' property must surface in the extracted field map.",
        );
        self::assertNull(
            $fields['blank'],
            "'null' scalar must round-trip as 'null'.",
        );
    }

    public function testExtractStringifiesResourceValues(): void
    {
        $handle = fopen('php://memory', 'rb');

        self::assertIsResource(
            $handle,
            "'fopen()' fixture must return a usable resource.",
        );

        $job = new class ($handle) {
            public function __construct(public mixed $stream) {}
        };

        $fields = JobPayloadInspector::extract($job);

        self::assertIsString(
            $fields['stream'] ?? null,
            'Resource value must be stringified.',
        );
        self::assertStringStartsWith(
            '(resource: ',
            $fields['stream'],
            "Resource label must use the '(resource: <type>)' format.",
        );

        fclose($handle);
    }
}
