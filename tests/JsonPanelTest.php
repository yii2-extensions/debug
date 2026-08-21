<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\JsonPanel;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for the generic JSON fallback panel.
 */
#[Group('panel')]
final class JsonPanelTest extends TestCase
{
    public function testGetNameNormalizesEverySupportedSeparator(): void
    {
        $panel = new JsonPanel();
        $panel->id = '__custom_panel-id.test__';

        self::assertSame(
            'Custom Panel Id Test',
            $panel->getName(),
            'Method should normalize panel ID by replacing underscores, hyphens, and dots with spaces and capitalizing words.',
        );
    }

    public function testGetNameFallsBackWhenIdContainsOnlyTrimmedSeparators(): void
    {
        $panel = new JsonPanel();

        $panel->id = '___';

        self::assertSame(
            'Panel',
            $panel->getName(),
            'Method should return "Panel" when the ID contains only separators that are trimmed away.',
        );
    }
}
