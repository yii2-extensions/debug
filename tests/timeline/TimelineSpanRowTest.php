<?php

declare(strict_types=1);

namespace yii\debug\tests\timeline;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\timeline\TimelineSpanRow;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see TimelineSpanRow} covering loose-array narrowing, category → CSS-variant mapping, the
 * minimum-width floor and the multi-line tooltip composition.
 */
#[Group('panel')]
#[Group('timeline')]
final class TimelineSpanRowTest extends TestCase
{
    public function testFromClampsBarWidthToVisibleFloor(): void
    {
        $row = TimelineSpanRow::from(
            [
                'category' => 'x',
                'css' => [
                    'width' => 0.1,
                    'left' => 5,
                ],
            ],
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
            [
                'category' => 'yii\\db\\Command::query',
                'info' => 'SELECT *',
                'duration' => 12.345,
                'memory' => 1572864,
                'memoryDiff' => 1048576,
            ],
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
            [
                'category' => 'x',
                'duration' => 1.0,
                'memory' => 1048576,
                'memoryDiff' => -1048576,
            ],
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
            [
                'category' => 'x',
                'duration' => 1.0,
                'memory' => 0,
            ],
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
            [
                'category' => 'yii\\db\\Command::query',
                'duration' => 1.0,
            ],
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
            TimelineSpanRow::from(['category' => 'yii\\base\\Application::handleRequest'])->variant,
            'Application spans must map to `app`.',
        );
        self::assertSame(
            'app',
            TimelineSpanRow::from(['category' => 'MyController::behaviors'])->variant,
            'Controller-only spans must map to `app`.',
        );
        self::assertSame(
            'app',
            TimelineSpanRow::from(['category' => 'app\\controllers\\site'])->variant,
            'Lowercase controllers namespace must map to `app`.',
        );
        self::assertSame(
            'app',
            TimelineSpanRow::from(['category' => 'yii\\base\\Module::runAction'])->variant,
            'Action dispatch spans must map to `app`.',
        );
    }

    public function testFromMapsCacheCategoryToCacheVariant(): void
    {
        self::assertSame(
            'cache',
            TimelineSpanRow::from(['category' => 'yii\\caching\\FileCache::get'])->variant,
            'Cache class spans must map to `cache`.',
        );
        self::assertSame(
            'cache',
            TimelineSpanRow::from(['category' => 'cache.something'])->variant,
            'Lowercase cache spans must map to `cache`.',
        );
    }

    public function testFromMapsDbCategoryToDbVariant(): void
    {
        self::assertSame(
            'db',
            TimelineSpanRow::from(['category' => 'yii\\db\\Connection::open'])->variant,
            'Namespace-only db spans must map to `db`.',
        );
        self::assertSame(
            'db',
            TimelineSpanRow::from(['category' => 'SomeCommand::execute'])->variant,
            'Command spans must map to `db`.',
        );
    }

    public function testFromMapsMailAndQueueCategoriesToTheirOwnVariants(): void
    {
        self::assertSame(
            'mail',
            TimelineSpanRow::from(['category' => 'app\\jobs\\mail'])->variant,
            'Mail spans must map to `mail`.',
        );
        self::assertSame(
            'mail',
            TimelineSpanRow::from(['category' => 'SendMailer::send'])->variant,
            'Capitalized Mail spans must map to `mail`.',
        );
        self::assertSame(
            'queue',
            TimelineSpanRow::from(['category' => 'queue.push'])->variant,
            'Queue spans must map to `queue`.',
        );
        self::assertSame(
            'queue',
            TimelineSpanRow::from(['category' => 'AppQueueWorker::run'])->variant,
            'Capitalized Queue spans must map to `queue`.',
        );
    }

    public function testFromMapsUnknownCategoryToOtherVariant(): void
    {
        self::assertSame(
            'other',
            TimelineSpanRow::from(['category' => 'my\\custom\\thing'])->variant,
            'Unknown spans must map to `other`.',
        );
        self::assertSame(
            'other',
            TimelineSpanRow::from(['category' => ''])->variant,
            'Empty category must map to `other`.',
        );
    }

    public function testFromMapsViewRenderTwigCategoryToViewVariant(): void
    {
        self::assertSame(
            'view',
            TimelineSpanRow::from(['category' => 'app\\components\\View::init'])->variant,
            'View-only spans must map to `view`.',
        );
        self::assertSame(
            'view',
            TimelineSpanRow::from(['category' => 'blade.render'])->variant,
            'Render-only spans must map to `view`.',
        );
        self::assertSame(
            'view',
            TimelineSpanRow::from(['category' => 'twig.compile'])->variant,
            'Twig-only spans must map to `view`.',
        );
    }

    public function testFromNarrowsMissingFieldsToSafeDefaults(): void
    {
        $row = TimelineSpanRow::from([]);

        self::assertSame(
            '',
            $row->category,
            'Missing category must default to empty string.',
        );
        self::assertSame(
            0.0,
            $row->duration,
            "Missing duration must default to '0.0'.",
        );
        self::assertSame(
            0,
            $row->depth,
            "Missing child depth must default to '0'.",
        );
        self::assertSame(
            '0',
            $row->cssLeft,
            "Missing left must default to '0'.",
        );
        self::assertSame(
            '0.4',
            $row->cssWidth,
            "Missing width must default to the floor '0.4'.",
        );
        self::assertSame(
            'other',
            $row->variant,
            'Missing category must yield the `other` variant.',
        );
    }
}
