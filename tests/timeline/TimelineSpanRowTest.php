<?php

declare(strict_types=1);

namespace yii\debug\tests\timeline;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\profile\ProfileRow;
use yii\debug\panels\timeline\TimelineSpanRow;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see TimelineSpanRow} covering the category → CSS-variant mapping, the minimum-width floor and
 * the multi-line tooltip composition.
 */
#[Group('panel')]
#[Group('timeline')]
final class TimelineSpanRowTest extends TestCase
{
    public function testFromClampsBarWidthToVisibleFloor(): void
    {
        $row = TimelineSpanRow::from(
            self::block(
                category: 'x',
                info: '',
                duration: 0.0,
                memory: 0,
                memoryDiff: 0,
            ),
            0,
            5,
            0.1,
        );

        self::assertSame(
            '0.4',
            $row->cssWidth,
            "Sub-floor widths must clamp to '0.4' to stay visible.",
        );
    }

    public function testFromComposesTooltipWithMemoryDeltaWhenNonZero(): void
    {
        $row = TimelineSpanRow::from(
            self::block(
                category: 'yii\\db\\Command::query',
                info: 'SELECT *',
                duration: 12.345,
                memory: 1572864,
                memoryDiff: 1048576,
            ),
            0,
            0.0,
            100.0,
        );

        self::assertStringContainsString(
            'SELECT *',
            $row->tooltip,
            'Info text must precede the duration line in the tooltip.',
        );
        self::assertStringContainsString(
            '12.345 ms',
            $row->tooltip,
            'Duration must render with three decimals.',
        );
        self::assertStringContainsString(
            '1.50 MB',
            $row->tooltip,
            'Memory must render as MB with two decimals.',
        );
        self::assertStringContainsString(
            '(+1.00 MB)',
            $row->tooltip,
            "Positive memory delta must show a '+' sign.",
        );
    }

    public function testFromComposesTooltipWithNegativeMemoryDeltaSign(): void
    {
        $row = TimelineSpanRow::from(
            self::block(
                category: 'x',
                info: '',
                duration: 1.0,
                memory: 1048576,
                memoryDiff: -1048576,
            ),
            0,
            0.0,
            100.0,
        );

        self::assertStringContainsString(
            '(−1.00 MB)',
            $row->tooltip,
            'Negative delta must use a minus sign character.',
        );
    }

    public function testFromComposesTooltipWithoutMemoryDeltaWhenZero(): void
    {
        $row = TimelineSpanRow::from(
            self::block(
                category: 'x',
                info: '',
                duration: 1.0,
                memory: 0,
                memoryDiff: 0,
            ),
            0,
            0.0,
            100.0,
        );

        self::assertStringNotContainsString(
            '(',
            $row->tooltip,
            'Zero delta must omit the parenthesized chip.',
        );
    }

    public function testFromFallsBackToCategoryWhenInfoIsMissing(): void
    {
        $row = TimelineSpanRow::from(
            self::block(
                category: 'yii\\db\\Command::query',
                info: '',
                duration: 1.0,
                memory: 0,
                memoryDiff: 0,
            ),
            0,
            0.0,
            100.0,
        );

        self::assertStringStartsWith(
            'yii\\db\\Command::query',
            $row->tooltip,
            'Missing info must fall back to the category for the tooltip heading.',
        );
    }

    public function testFromMapsApplicationControllerCategoryToAppVariant(): void
    {
        self::assertSame(
            'app',
            self::spanFor('yii\\base\\Application::handleRequest')->variant,
            'Application spans must map to `app`.',
        );
        self::assertSame(
            'app',
            self::spanFor('MyController::behaviors')->variant,
            'Controller-only spans must map to `app`.',
        );
        self::assertSame(
            'app',
            self::spanFor('app\\controllers\\site')->variant,
            'Lowercase controllers namespace must map to `app`.',
        );
        self::assertSame(
            'app',
            self::spanFor('yii\\base\\Module::runAction')->variant,
            'Action dispatch spans must map to `app`.',
        );
    }

    public function testFromMapsCacheCategoryToCacheVariant(): void
    {
        self::assertSame(
            'cache',
            self::spanFor('yii\\caching\\FileCache::get')->variant,
            'Cache class spans must map to `cache`.',
        );
        self::assertSame(
            'cache',
            self::spanFor('cache.something')->variant,
            'Lowercase cache spans must map to `cache`.',
        );
    }

    public function testFromMapsDbCategoryToDbVariant(): void
    {
        self::assertSame(
            'db',
            self::spanFor('yii\\db\\Connection::open')->variant,
            'Namespace-only db spans must map to `db`.',
        );
        self::assertSame(
            'db',
            self::spanFor('SomeCommand::execute')->variant,
            'Command spans must map to `db`.',
        );
    }

    public function testFromMapsMailAndQueueCategoriesToTheirOwnVariants(): void
    {
        self::assertSame(
            'mail',
            self::spanFor('app\\jobs\\mail')->variant,
            'Mail spans must map to `mail`.',
        );
        self::assertSame(
            'mail',
            self::spanFor('SendMailer::send')->variant,
            'Capitalized Mail spans must map to `mail`.',
        );
        self::assertSame(
            'queue',
            self::spanFor('queue.push')->variant,
            'Queue spans must map to `queue`.',
        );
        self::assertSame(
            'queue',
            self::spanFor('AppQueueWorker::run')->variant,
            'Capitalized Queue spans must map to `queue`.',
        );
    }

    public function testFromMapsUnknownCategoryToOtherVariant(): void
    {
        self::assertSame(
            'other',
            self::spanFor('my\\custom\\thing')->variant,
            'Unknown spans must map to `other`.',
        );
        self::assertSame(
            'other',
            self::spanFor('')->variant,
            'Empty category must map to `other`.',
        );
    }

    public function testFromMapsViewRenderTwigCategoryToViewVariant(): void
    {
        self::assertSame(
            'view',
            self::spanFor('app\\components\\View::init')->variant,
            'View-only spans must map to `view`.',
        );
        self::assertSame(
            'view',
            self::spanFor('blade.render')->variant,
            'Render-only spans must map to `view`.',
        );
        self::assertSame(
            'view',
            self::spanFor('twig.compile')->variant,
            'Twig-only spans must map to `view`.',
        );
    }

    public function testFromRendersAnEmptyBlockWithSafeDefaults(): void
    {
        $row = TimelineSpanRow::from(self::block(), 0, 0.0, 0.0);

        self::assertSame('', $row->category, 'Empty category stays empty.');
        self::assertSame(0.0, $row->duration, 'Zero duration stays `0.0`.');
        self::assertSame(0, $row->depth, 'Depth stays `0`.');
        self::assertSame(
            '0',
            $row->cssLeft,
            "Zero left must render as '0'.",
        );
        self::assertSame(
            '0.4',
            $row->cssWidth,
            "Zero width must clamp to the floor '0.4'.",
        );
        self::assertSame(
            'other',
            $row->variant,
            'An empty category yields the `other` variant.',
        );
    }

    public function testFromUsesTheExactMebibyteDivisorForMemoryValues(): void
    {
        $first = TimelineSpanRow::from(
            self::block(memory: 131072, memoryDiff: 89129),
            0,
            0.0,
            100.0,
        );
        $second = TimelineSpanRow::from(
            self::block(memory: 89129, memoryDiff: 131072),
            0,
            0.0,
            100.0,
        );

        self::assertSame(
            "\n0.000 ms · 0.12 MB (+0.09 MB)",
            $first->tooltip,
            'Memory values must use exactly 1,048,576 bytes per mebibyte.',
        );
        self::assertSame(
            "\n0.000 ms · 0.09 MB (+0.12 MB)",
            $second->tooltip,
            'Rounding must remain stable on both sides of a two-decimal boundary.',
        );
    }

    private static function block(
        string $category = '',
        string $info = '',
        float $duration = 0.0,
        int $memory = 0,
        int $memoryDiff = 0,
    ): ProfileRow {
        return new ProfileRow(
            timestamp: 0.0,
            duration: $duration,
            category: $category,
            info: $info,
            level: 0,
            seq: 0,
            memory: $memory,
            memoryDiff: $memoryDiff,
            trace: [],
        );
    }

    private static function spanFor(string $category): TimelineSpanRow
    {
        return TimelineSpanRow::from(self::block(category: $category), 0, 0.0, 100.0);
    }
}
