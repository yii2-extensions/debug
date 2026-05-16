<?php

declare(strict_types=1);

namespace yii\debug\tests\event;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\event\EventRowNormalizer;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see EventRowNormalizer} covering the narrowing of GridView callback arguments into a typed
 * {@see \yii\debug\panels\event\EventRow}.
 */
#[Group('panel')]
#[Group('event')]
final class EventRowNormalizerTest extends TestCase
{
    public function testFromCoercesNumericStringToFloatTime(): void
    {
        $row = EventRowNormalizer::from(
            ['time' => '1700000000.5'],
        );

        self::assertSame(
            1_700_000_000.5,
            $row->time,
            'Numeric string must coerce to float.',
        );
    }

    public function testFromFallsBackToEmptyStringWhenStringFieldsAreNotStrings(): void
    {
        $row = EventRowNormalizer::from(
            [
                'name' => 42,
                'class' => null,
                'senderClass' => false,
            ],
        );

        self::assertSame(
            '',
            $row->name,
            "Non-string 'name' must collapse to ''.",
        );
        self::assertSame(
            '',
            $row->class,
            "Non-string 'class' must collapse to ''.",
        );
        self::assertSame(
            '',
            $row->senderClass,
            "Non-string 'senderClass' must collapse to ''.",
        );
    }

    public function testFromFallsBackToZeroWhenTimeIsNotNumeric(): void
    {
        $row = EventRowNormalizer::from(
            ['time' => 'abc'],
        );

        self::assertSame(
            0.0,
            $row->time,
            "Non-numeric 'time' must collapse to '0.0'.",
        );
    }

    public function testFromMapsAnyOtherIsStaticVariantToZero(): void
    {
        self::assertSame(
            '0',
            EventRowNormalizer::from(['isStatic' => '0'])->isStatic,
            "String '0' must map to '0'.",
        );
        self::assertSame(
            '0',
            EventRowNormalizer::from(['isStatic' => 0])->isStatic,
            "Int '0' must map to '0'.",
        );
        self::assertSame(
            '0',
            EventRowNormalizer::from(['isStatic' => false])->isStatic,
            "'false' must map to '0'.",
        );
        self::assertSame(
            '0',
            EventRowNormalizer::from(['isStatic' => null])->isStatic,
            "'null' must map to '0'.",
        );
        self::assertSame(
            '0',
            EventRowNormalizer::from(['isStatic' => 'truthy'])->isStatic,
            "Arbitrary string must map to '0'.",
        );
        self::assertSame(
            '0',
            EventRowNormalizer::from([])->isStatic,
            "Missing 'isStatic' must default to '0'.",
        );
    }

    public function testFromMapsTruthyIsStaticVariantsToOne(): void
    {
        self::assertSame(
            '1',
            EventRowNormalizer::from(['isStatic' => '1'])->isStatic,
            "String '1' must map to '1'.",
        );
        self::assertSame(
            '1',
            EventRowNormalizer::from(['isStatic' => 1])->isStatic,
            "Int '1' must map to '1'.",
        );
        self::assertSame(
            '1',
            EventRowNormalizer::from(['isStatic' => true])->isStatic,
            "'true' must map to '1'.",
        );
    }

    public function testFromReturnsAllZeroDefaultsWhenInputIsNotArray(): void
    {
        $row = EventRowNormalizer::from(
            'not an array',
        );

        self::assertSame(
            0.0,
            $row->time,
            "Non-array input must yield zero 'time'.",
        );
        self::assertSame(
            '',
            $row->name,
            "Non-array input must yield empty 'name'.",
        );
        self::assertSame(
            '',
            $row->class,
            "Non-array input must yield empty 'class'.",
        );
        self::assertSame(
            '0',
            $row->isStatic,
            "Non-array input must yield '0' 'isStatic'.",
        );
        self::assertSame(
            '',
            $row->senderClass,
            "Non-array input must yield empty 'senderClass'.",
        );
    }

    public function testFromRoundTripsTypedRow(): void
    {
        $row = EventRowNormalizer::from(
            [
                'time' => 1_700_000_000.789,
                'name' => 'EVENT_AFTER_REQUEST',
                'class' => 'yii\\base\\Event',
                'isStatic' => '1',
                'senderClass' => 'yii\\web\\Application',
            ],
        );

        self::assertSame(
            1_700_000_000.789,
            $row->time,
            'Time must round-trip.',
        );
        self::assertSame(
            'EVENT_AFTER_REQUEST',
            $row->name,
            'Name must round-trip.',
        );
        self::assertSame(
            'yii\\base\\Event',
            $row->class,
            'Class must round-trip.',
        );
        self::assertSame(
            '1',
            $row->isStatic,
            'isStatic must round-trip.',
        );
        self::assertSame(
            'yii\\web\\Application',
            $row->senderClass,
            'senderClass must round-trip.',
        );
    }
}
