<?php

declare(strict_types=1);

namespace yii\debug\tests\log;

use PHPForge\Debug\Storage\RequestSummary;
use PHPUnit\Framework\Attributes\{Group, TestWith};
use Yii;
use yii\debug\{LogTarget, Module};
use yii\debug\tests\support\TestCase;
use yii\helpers\FileHelper;

/**
 * Tests request metadata fallbacks and application-owned log-target extension hooks through persisted captures.
 */
#[Group('log-target')]
final class LogTargetBoundaryTest extends TestCase
{
    public function testApplicationLogTargetHooksReachThePersistedSummary(): void
    {
        $module = $this->module();

        $target = new class ($module) extends LogTarget {
            protected function collectSummary(): RequestSummary
            {
                return parent::collectSummary()->withResponse(299);
            }

            protected function getExcessiveDbCallersCount(): int
            {
                return 4;
            }

            protected function getSqlTotalCount(): int
            {
                return 37;
            }
        };

        $summary = $this->persist($module, $target);

        self::assertSame(
            299,
            $summary->statusCode,
            'An application override must augment the exported summary.',
        );
        self::assertSame(
            37,
            $summary->sqlCount,
            'Custom database counters must reach persistent history.',
        );
        self::assertSame(
            4,
            $summary->excessiveCallersCount,
            'Custom excessive-query counters must reach persistent history.',
        );
    }

    #[TestWith(['invalid-request-time'])]
    #[TestWith([false])]
    #[TestWith([[]])]
    public function testInvalidRequestTimeUsesTheCaptureClockAndPreservesTheClientAddress(mixed $time): void
    {
        $module = $this->module();

        $_SERVER['REQUEST_TIME_FLOAT'] = $time;
        $_SERVER['REMOTE_ADDR'] = '192.0.2.123';
        $before = microtime(true);

        $summary = $this->persist($module, new LogTarget($module));

        self::assertGreaterThanOrEqual(
            $before,
            $summary->time,
            'Invalid request timestamps must use the current capture clock.',
        );
        self::assertLessThanOrEqual(
            microtime(true),
            $summary->time,
            'The fallback must represent this capture.',
        );
        self::assertSame(
            '192.0.2.123',
            $summary->ip,
            'A present client address must survive summary capture.',
        );
    }

    private function module(): Module
    {
        $this->mockWebApplication();

        Yii::$app->request->setUrl('/synthetic-summary');

        return new Module('debug', null, ['dataPath' => sys_get_temp_dir() . '/debug-summary-' . uniqid()]);
    }

    private function persist(Module $module, LogTarget $target): RequestSummary
    {
        $module->logTarget = $target;

        try {
            $target->export();
            $snapshot = $target->loadSnapshot($target->tag);

            self::assertNotNull(
                $snapshot,
                'The public export must write a readable snapshot.',
            );

            return $snapshot->summary;
        } finally {
            FileHelper::removeDirectory($module->dataPath);
        }
    }
}
