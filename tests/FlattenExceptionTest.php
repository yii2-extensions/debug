<?php

declare(strict_types=1);

namespace yiiunit\debug;

use Exception;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\FlattenException;
use yii\web\NotFoundHttpException;

/**
 * Unit tests for {@see FlattenException} covering message/code/trace flattening, serialization
 * boundaries, recursion detection, and large-payload truncation.
 *
 * @author Dmitry Bashkarev <dmitry@bashkarev.com>
 * @since 2.0.10
 */
#[Group('flatten-exception')]
final class FlattenExceptionTest extends TestCase
{
    public function testClosureArgumentsRemainSerializable(): void
    {
        $exception = $this->createException(static fn(): int => 1 + 1);
        $flattened = new FlattenException($exception);

        self::assertStringContainsString(
            'Closure',
            serialize($flattened),
            'Serialized form must mention Closure rather than throwing on serialization.',
        );
    }

    public function testFlattenedArgumentsAreNormalizedByType(): void
    {
        $dh = opendir(__DIR__);
        $fh = tmpfile();

        self::assertIsResource($dh, 'opendir() must hand back an iterable directory stream for the fixture path.');
        self::assertIsResource($fh, 'tmpfile() must hand back a usable temporary stream.');

        $incomplete = unserialize('O:14:"BogusTestClass":0:{}');

        $exception = $this->createException([
            (object) ['foo' => 1],
            new NotFoundHttpException(),
            $incomplete,
            $dh,
            $fh,
            static fn(): int => 0,
            [1, 2],
            ['foo' => 123],
            null,
            true,
            false,
            0,
            0.0,
            '0',
            '',
            INF,
            NAN,
        ]);

        $flattened = new FlattenException($exception);
        $argsList = $this->extractArgsList($flattened);

        closedir($dh);
        fclose($fh);

        $get = static function (int $position) use ($argsList): array {
            self::assertArrayHasKey($position, $argsList, "Argument list must expose slot {$position}.");

            return $argsList[$position];
        };

        self::assertSame(['object', 'stdClass'], $get(0), 'stdClass instances must be tagged `object`.');
        self::assertSame(['object', 'yii\web\NotFoundHttpException'], $get(1), 'Yii exception type name must be retained.');
        self::assertSame(['incomplete-object', 'BogusTestClass'], $get(2), '__PHP_Incomplete_Class must be tagged separately.');
        self::assertSame(['resource', 'stream'], $get(3), 'Stream resources must be tagged with their type name.');
        self::assertSame(['resource', 'stream'], $get(4), 'Temporary file streams must be tagged the same as opendir streams.');

        $closureArgs = $get(5);

        self::assertSame('object', $closureArgs[0], 'Closures must be tagged `object`.');

        $closureClass = $closureArgs[1];

        self::assertIsString($closureClass, 'Closure tag payload must surface as the class name string.');
        self::assertTrue(
            $closureClass === 'Closure' || is_subclass_of($closureClass, 'Closure'),
            'Closure tag must be Closure or a subclass thereof.',
        );

        self::assertSame(['array', [['integer', 1], ['integer', 2]]], $get(6), 'Numeric arrays must keep value tags per element.');
        self::assertSame(['array', ['foo' => ['integer', 123]]], $get(7), 'Associative arrays must preserve string keys.');
        self::assertSame(['null', null], $get(8), 'Null must be tagged with a null payload.');
        self::assertSame(['boolean', true], $get(9), 'True must be tagged `boolean`.');
        self::assertSame(['boolean', false], $get(10), 'False must be tagged `boolean`.');
        self::assertSame(['integer', 0], $get(11), 'Integer 0 must be preserved as integer.');
        self::assertSame(['float', 0.0], $get(12), 'Float 0.0 must be preserved as float.');
        self::assertSame(['string', '0'], $get(13), 'String "0" must be preserved as string.');
        self::assertSame(['string', ''], $get(14), 'Empty string must be preserved as string.');
        self::assertSame(['float', INF], $get(15), 'Infinity must round-trip as float.');

        $nanEntry = $get(16);

        self::assertSame('float', $nanEntry[0], 'NaN must still be tagged float.');
        self::assertNan($nanEntry[1], 'NaN payload must remain a NaN value.');
    }

    public function testGetClassExposesOriginalClassName(): void
    {
        self::assertSame(
            'Exception',
            (new FlattenException(new Exception()))->getClass(),
            'getClass must expose the original exception class name.',
        );
    }

    public function testGetCodeMirrorsOriginalException(): void
    {
        $flattened = new FlattenException(new Exception('test', 100));

        self::assertSame(100, $flattened->getCode(), 'Code must round-trip from the original exception.');
    }

    public function testGetFileMatchesThrowSiteFile(): void
    {
        $flattened = new FlattenException(new Exception('test', 100));

        self::assertSame(__FILE__, $flattened->getFile(), 'File must point to the throw site of the source exception.');
    }

    public function testGetLineMatchesThrowSiteLine(): void
    {
        $flattened = new FlattenException(new Exception('test', 100));
        self::assertSame(__LINE__ - 1, $flattened->getLine(), 'Line must point to the throw site of the source exception.');
    }

    public function testGetMessageMirrorsOriginalException(): void
    {
        $flattened = new FlattenException(new Exception('test'));

        self::assertSame('test', $flattened->getMessage(), 'Message must round-trip from the original exception.');
    }

    public function testGetPreviousReturnsFlattenedChain(): void
    {
        $exception2 = new Exception();
        $exception = new Exception('test', 0, $exception2);

        $flattened = new FlattenException($exception);
        $flattened2 = new FlattenException($exception2);

        $previous = $flattened->getPrevious();

        self::assertInstanceOf(FlattenException::class, $previous, 'getPrevious must yield a flattened chain link.');
        self::assertSame(
            $flattened2->getTrace(),
            $previous->getTrace(),
            'Previous chain must mirror the trace of the wrapped exception.',
        );
    }

    public function testGetTraceAsStringMatchesOriginal(): void
    {
        $exception = $this->createException('test');

        self::assertSame(
            $exception->getTraceAsString(),
            (new FlattenException($exception))->getTraceAsString(),
            'String trace must mirror the original exception output exactly.',
        );
    }

    public function testGetTraceCapturesNamespaceClassAndFunction(): void
    {
        $exception = new Exception('test');
        $flattened = new FlattenException($exception);

        $trace = $flattened->getTrace();

        self::assertNotEmpty($trace, 'Flattened trace must include the throw-site frame.');

        $frame = $trace[0];

        self::assertSame(__NAMESPACE__, $frame['namespace'], 'Trace frame must carry the throw-site namespace.');
        self::assertSame(__CLASS__, $frame['class'], 'Trace frame must carry the FQCN of the throw site.');
        self::assertSame('FlattenExceptionTest', $frame['short_class'], 'Short class name must drop the namespace.');
        self::assertSame(__FUNCTION__, $frame['function'], 'Trace frame must carry the throw-site method name.');
    }

    public function testOversizedArgumentArraysAreTruncated(): void
    {
        $a = [];
        for ($i = 0; $i < 20; ++$i) {
            for ($j = 0; $j < 50; ++$j) {
                for ($k = 0; $k < 10; ++$k) {
                    $a[$i][$j][$k] = 'value';
                }
            }
        }
        $a[20] = 'value';
        $a[21] = 'value1';
        $exception = $this->createException($a);

        $flattened = new FlattenException($exception);
        $trace = $flattened->getTrace();

        self::assertNotEmpty($trace, 'Flattened trace must include the throw-site frame.');
        self::assertArrayHasKey(0, $trace[0]['args'], 'First flattened argument must be present.');
        self::assertSame(
            ['array', ['array', '*SKIPPED over 10000 entries*']],
            $trace[0]['args'][0],
            'Outer array must collapse to the SKIPPED sentinel beyond 10k entries.',
        );

        $serialized = serialize($trace);

        self::assertStringContainsString(
            '*SKIPPED over 10000 entries*',
            $serialized,
            'Serialized trace must retain the truncation sentinel.',
        );
        self::assertStringNotContainsString(
            '*value1*',
            $serialized,
            'Truncated payloads must not leak entries beyond the cap.',
        );
    }

    public function testRecursionInArgumentsIsCollapsed(): void
    {
        $a = ['foo'];
        $a[] = [2, &$a];
        $exception = $this->createException($a);

        $flattened = new FlattenException($exception);

        self::assertStringContainsString(
            '*DEEP NESTED ARRAY*',
            serialize($flattened->getTrace()),
            'Self-referential arrays must collapse to the *DEEP NESTED ARRAY* sentinel.',
        );
    }

    public function testToStringMatchesOriginalOnEmptyAndPopulatedExceptions(): void
    {
        $emptyException = new Exception();
        self::assertSame(
            $emptyException->__toString(),
            (new FlattenException($emptyException))->__toString(),
            '__toString must mirror the original on an empty exception.',
        );

        $populatedException = new Exception('test');
        self::assertSame(
            $populatedException->__toString(),
            (new FlattenException($populatedException))->__toString(),
            '__toString must mirror the original on a populated exception.',
        );
    }

    protected function setUp(): void
    {
        ini_set('zend.exception_ignore_args', '0');
    }

    private function createException(mixed $foo): Exception
    {
        return new Exception();
    }

    /**
     * Pulls the inner tagged-tuple list out of the first trace frame, asserting structural invariants along the way.
     *
     * @return array<int, array{0: string, 1: mixed}>
     */
    private function extractArgsList(FlattenException $flattened): array
    {
        $trace = $flattened->getTrace();

        self::assertNotEmpty($trace, 'FlattenException must capture at least one stack frame.');

        $args = $trace[0]['args'];

        self::assertArrayHasKey(0, $args, 'Outer tuple list must expose the first argument slot.');

        $outer = $args[0];

        self::assertIsArray($outer, 'Outer tuple slot must be a tagged tuple, not the SKIPPED sentinel.');

        $payload = $outer[1];

        self::assertIsArray($payload, 'Tagged array payload must be a list of tagged tuples.');

        $tuples = [];

        foreach ($payload as $index => $entry) {
            self::assertIsInt($index, 'Inner argument list must be numerically indexed.');
            self::assertIsArray($entry, 'Each inner argument must be a tagged tuple.');
            self::assertArrayHasKey(0, $entry, 'Tagged tuple must declare a `type` slot.');
            self::assertArrayHasKey(1, $entry, 'Tagged tuple must declare a `payload` slot.');

            $type = $entry[0];

            self::assertIsString($type, 'Tagged tuple `type` slot must be a string discriminator.');

            $tuples[$index] = [$type, $entry[1]];
        }

        return $tuples;
    }
}
