<?php

declare(strict_types=1);

namespace yii\debug\tests\helpers;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use yii\debug\helpers\Avatar;

/**
 * Unit tests for {@see Avatar} helper class.
 */
#[Group('avatar')]
#[Group('helpers')]
final class AvatarTest extends TestCase
{
    public function testHueForNormalizesCaseAndReturnsStableHue(): void
    {
        self::assertSame(
            335,
            Avatar::hueFor('Alice'),
            "'hueFor' must return a stable hue for a given seed, regardless of case.",
        );
        self::assertSame(
            Avatar::hueFor('Alice'),
            Avatar::hueFor('ALICE'),
            "'hueFor' must return the same hue for seeds that differ only in case.",
        );
    }

    public function testHueForReturnsFallbackForEmptySeed(): void
    {
        self::assertSame(
            210,
            Avatar::hueFor(''),
            "'hueFor' must return the fallback hue for empty seeds.",
        );
    }
}
