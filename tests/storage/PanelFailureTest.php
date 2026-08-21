<?php

declare(strict_types=1);

namespace yii\debug\tests\storage;

use PHPForge\Debug\Storage\{ExceptionSnapshot, HydrationException, PanelFailure};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for {@see PanelFailure} covering the isolated panel-failure envelope and its stage guard.
 */
#[Group('storage')]
final class PanelFailureTest extends TestCase
{
    public function testRoundTripsTheCapturedStageAndException(): void
    {
        $failure = PanelFailure::fromThrowable(PanelFailure::HYDRATE, new RuntimeException('boom'));

        $restored = PanelFailure::fromArray($failure->jsonSerialize(), '$.failures.log');

        self::assertSame(
            PanelFailure::HYDRATE,
            $restored->stage,
            'The stage must round-trip.',
        );
        self::assertSame(
            'boom',
            $restored->exception->getMessage(),
            'The message must round-trip.',
        );
    }

    public function testThrowHydrationExceptionForAnUnknownStage(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '\$.failures.log.stage'");

        PanelFailure::fromArray(
            [
                'stage' => 'render',
                'exception' => ExceptionSnapshot::fromThrowable(new RuntimeException('boom'))->jsonSerialize(),
            ],
            '$.failures.log',
        );
    }
}
