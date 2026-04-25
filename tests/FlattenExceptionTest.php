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
        $array = $flattened->getTrace()[0]['args'][0][1];

        closedir($dh);
        fclose($fh);

        $i = 0;
        self::assertSame(['object', 'stdClass'], $array[$i++], 'stdClass instances must be tagged `object`.');
        self::assertSame(['object', 'yii\web\NotFoundHttpException'], $array[$i++], 'Yii exception type name must be retained.');
        self::assertSame(['incomplete-object', 'BogusTestClass'], $array[$i++], '__PHP_Incomplete_Class must be tagged separately.');
        self::assertSame(['resource', 'stream'], $array[$i++], 'Stream resources must be tagged with their type name.');
        self::assertSame(['resource', 'stream'], $array[$i++], 'Temporary file streams must be tagged the same as opendir streams.');

        $closureArgs = $array[$i++];
        self::assertSame('object', $closureArgs[0], 'Closures must be tagged `object`.');
        self::assertTrue(
            $closureArgs[1] === 'Closure' || is_subclass_of($closureArgs[1], 'Closure'),
            'Closure tag must be Closure or a subclass thereof.',
        );

        self::assertSame(['array', [['integer', 1], ['integer', 2]]], $array[$i++], 'Numeric arrays must keep value tags per element.');
        self::assertSame(['array', ['foo' => ['integer', 123]]], $array[$i++], 'Associative arrays must preserve string keys.');
        self::assertSame(['null', null], $array[$i++], 'Null must be tagged with a null payload.');
        self::assertSame(['boolean', true], $array[$i++], 'True must be tagged `boolean`.');
        self::assertSame(['boolean', false], $array[$i++], 'False must be tagged `boolean`.');
        self::assertSame(['integer', 0], $array[$i++], 'Integer 0 must be preserved as integer.');
        self::assertSame(['float', 0.0], $array[$i++], 'Float 0.0 must be preserved as float.');
        self::assertSame(['string', '0'], $array[$i++], 'String "0" must be preserved as string.');
        self::assertSame(['string', ''], $array[$i++], 'Empty string must be preserved as string.');
        self::assertSame(['float', INF], $array[$i++], 'Infinity must round-trip as float.');

        self::assertSame('float', $array[$i][0], 'NaN must still be tagged float.');
        self::assertNan($array[$i++][1], 'NaN payload must remain a NaN value.');
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

        self::assertSame(
            $flattened2->getTrace(),
            $flattened->getPrevious()->getTrace(),
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

        self::assertSame(__NAMESPACE__, $trace[0]['namespace'], 'Trace frame must carry the throw-site namespace.');
        self::assertSame(__CLASS__, $trace[0]['class'], 'Trace frame must carry the FQCN of the throw site.');
        self::assertSame('FlattenExceptionTest', $trace[0]['short_class'], 'Short class name must drop the namespace.');
        self::assertSame(__FUNCTION__, $trace[0]['function'], 'Trace frame must carry the throw-site method name.');
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
}
