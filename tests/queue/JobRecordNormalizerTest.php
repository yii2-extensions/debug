<?php

declare(strict_types=1);

namespace yii\debug\tests\queue;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\queue\JobRecordNormalizer;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see JobRecordNormalizer} covering the narrowing of saved queue rows into a typed
 * {@see \yii\debug\panels\queue\JobRecord}.
 */
#[Group('panel')]
#[Group('queue')]
final class JobRecordNormalizerTest extends TestCase
{
    public function testFromAcceptsEachKnownEventType(): void
    {
        self::assertSame(
            'push',
            JobRecordNormalizer::from(['eventType' => 'push'])->eventType,
            "'push' must round-trip.",
        );
        self::assertSame(
            'exec',
            JobRecordNormalizer::from(['eventType' => 'exec'])->eventType,
            "'exec' must round-trip.",
        );
        self::assertSame(
            'error',
            JobRecordNormalizer::from(['eventType' => 'error'])->eventType,
            "'error' must round-trip.",
        );
    }

    public function testFromCoercesNumericStringsToFloatAndInt(): void
    {
        $record = JobRecordNormalizer::from(
            [
                'time' => '1700000000.5',
                'ttr' => '30',
                'delay' => '5',
                'priority' => '10',
                'attempt' => '2',
                'duration' => '0.123',
            ],
        );

        self::assertSame(
            1_700_000_000.5,
            $record->time,
            'Numeric string time must coerce to float.',
        );
        self::assertSame(
            30,
            $record->ttr,
            'Numeric string ttr must coerce to int.',
        );
        self::assertSame(
            5,
            $record->delay,
            'Numeric string delay must coerce to int.',
        );
        self::assertSame(
            10,
            $record->priority,
            'Numeric string priority must coerce to int.',
        );
        self::assertSame(
            2,
            $record->attempt,
            'Numeric string attempt must coerce to int.',
        );
        self::assertSame(
            0.123,
            $record->duration,
            'Numeric string duration must coerce to float.',
        );
    }

    public function testFromCollapsesUnknownEventTypeToPush(): void
    {
        $record = JobRecordNormalizer::from(
            ['eventType' => 'cancelled'],
        );

        self::assertSame(
            'push',
            $record->eventType,
            "Unknown `eventType` must collapse to 'push'.",
        );
    }

    public function testFromFallsBackToEmptyStringWhenStringFieldsAreNotStrings(): void
    {
        $record = JobRecordNormalizer::from(
            [
                'componentId' => 42,
                'jobClass' => null,
                'payloadFields' => 'invalid',
                'jobId' => ['nested'],
                'error' => 0.5,
            ],
        );

        self::assertSame(
            '',
            $record->componentId,
            "Non-string componentId must collapse to ''.",
        );
        self::assertSame(
            '',
            $record->jobClass,
            "Non-string jobClass must collapse to ''.",
        );
        self::assertSame(
            [],
            $record->payloadFields,
            "Non-array `payloadFields` must collapse to '[]'.",
        );
        self::assertSame(
            '',
            $record->jobId,
            "Non-string jobId must collapse to ''.",
        );
        self::assertSame(
            '',
            $record->error,
            "Non-string error must collapse to ''.",
        );
    }

    public function testFromKeepsNullableNumericFieldsAsNullWhenAbsent(): void
    {
        $record = JobRecordNormalizer::from(
            [],
        );

        self::assertNull(
            $record->ttr,
            "Missing ttr must remain 'null'.",
        );
        self::assertNull(
            $record->delay,
            "Missing delay must remain 'null'.",
        );
        self::assertNull(
            $record->priority,
            "Missing priority must remain 'null'.",
        );
        self::assertNull(
            $record->attempt,
            "Missing attempt must remain 'null'.",
        );
        self::assertNull(
            $record->duration,
            "Missing duration must remain 'null'.",
        );
    }

    public function testFromReturnsAllEmptyDefaultsWhenInputIsNotArray(): void
    {
        $record = JobRecordNormalizer::from(
            'not an array',
        );

        self::assertSame(
            'push',
            $record->eventType,
            "Non-array input must default 'eventType' to 'push'.",
        );
        self::assertSame(
            '',
            $record->componentId,
            "Non-array input must yield empty 'componentId'.",
        );
        self::assertSame(
            '',
            $record->jobClass,
            "Non-array input must yield empty 'jobClass'.",
        );
        self::assertSame(
            [],
            $record->payloadFields,
            "Non-array input must yield empty 'payloadFields'.",
        );
        self::assertSame(
            '',
            $record->driverName,
            "Non-array input must yield empty 'driverName'.",
        );
        self::assertSame(
            '',
            $record->driverClass,
            "Non-array input must yield empty 'driverClass'.",
        );
        self::assertFalse(
            $record->isAsync,
            "Non-array input must yield 'isAsync = false'.",
        );
        self::assertSame(
            0.0,
            $record->time,
            "Non-array input must yield zero 'time'.",
        );
        self::assertSame(
            '',
            $record->jobId,
            "Non-array input must yield empty 'jobId'.",
        );
        self::assertNull(
            $record->ttr,
            "Non-array input must yield 'null' 'ttr'.",
        );
        self::assertNull(
            $record->delay,
            "Non-array input must yield 'null' 'delay'.",
        );
        self::assertNull(
            $record->priority,
            "Non-array input must yield 'null' 'priority'.",
        );
        self::assertNull(
            $record->attempt,
            "Non-array input must yield 'null' 'attempt'.",
        );
        self::assertNull(
            $record->duration,
            "Non-array input must yield 'null' 'duration'.",
        );
        self::assertSame(
            '',
            $record->error,
            "Non-array input must yield empty 'error'.",
        );
    }

    public function testFromRoundTripsTypedRow(): void
    {
        $record = JobRecordNormalizer::from(
            [
                'eventType' => 'exec',
                'componentId' => 'queueEmail',
                'jobClass' => 'app\\jobs\\HelloJob',
                'payloadFields' => ['message' => 'first'],
                'driverName' => 'Sync',
                'driverClass' => 'yii\\queue\\sync\\Queue',
                'isAsync' => false,
                'time' => 1_700_000_000.5,
                'jobId' => 'msg-7',
                'ttr' => 30,
                'delay' => 5,
                'priority' => 10,
                'attempt' => 2,
                'duration' => 0.123,
                'error' => '',
            ],
        );

        self::assertSame(
            'exec',
            $record->eventType,
            "'eventType' must round-trip.",
        );
        self::assertSame(
            'queueEmail',
            $record->componentId,
            "'componentId' must round-trip.",
        );
        self::assertSame(
            'app\\jobs\\HelloJob',
            $record->jobClass,
            "'jobClass' must round-trip.",
        );
        self::assertSame(
            ['message' => 'first'],
            $record->payloadFields,
            "'payloadFields' must round-trip.",
        );
        self::assertSame(
            'Sync',
            $record->driverName,
            "'driverName' must round-trip.",
        );
        self::assertSame(
            'yii\\queue\\sync\\Queue',
            $record->driverClass,
            "'driverClass' must round-trip.",
        );
        self::assertFalse(
            $record->isAsync,
            "'isAsync' must round-trip.",
        );
        self::assertSame(
            1_700_000_000.5,
            $record->time,
            "'time' must round-trip.",
        );
        self::assertSame(
            'msg-7',
            $record->jobId,
            "'jobId' must round-trip.",
        );
        self::assertSame(
            30,
            $record->ttr,
            "'ttr' must round-trip.",
        );
        self::assertSame(
            5,
            $record->delay,
            "'delay' must round-trip.",
        );
        self::assertSame(
            10,
            $record->priority,
            "'priority' must round-trip.",
        );
        self::assertSame(
            2,
            $record->attempt,
            "'attempt' must round-trip.",
        );
        self::assertSame(
            0.123,
            $record->duration,
            "'duration' must round-trip.",
        );
    }
}
