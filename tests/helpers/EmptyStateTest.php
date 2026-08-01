<?php

declare(strict_types=1);

namespace yii\debug\tests\helpers;

use PHPUnit\Framework\Attributes\Group;
use UIAwesome\Html\Flow\P;
use yii\debug\helpers\EmptyState;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see EmptyState} covering the shared empty-state card: container class, headline encoding, and
 * body-element ordering.
 */
#[Group('helpers')]
#[Group('empty-state')]
final class EmptyStateTest extends TestCase
{
    public function testCardEncodesHeadlineText(): void
    {
        self::assertStringContainsString(
            '&lt;script&gt;',
            EmptyState::card('<script>alert(1)</script>'),
            'Headline must be HTML-escaped.',
        );
    }

    public function testCardRendersBodyChildrenInOrder(): void
    {
        $card = EmptyState::card(
            'Nothing captured',
            P::tag()->content('first'),
            P::tag()->content('second'),
        );

        self::assertMatchesRegularExpression(
            '~Nothing captured.*first.*second~s',
            $card,
            'Order: headline, then body elements.',
        );
    }

    public function testCardWrapsHeadlineInEmptyStateContainer(): void
    {
        $card = EmptyState::card('Nothing captured');

        self::assertStringContainsString(
            'yii-debug-empty-state',
            $card,
            'Container class must be present.',
        );
        self::assertMatchesRegularExpression(
            '~<h2>\s*Nothing captured\s*</h2>~',
            $card,
            'Headline must render as an `<h2>`.',
        );
    }
}
