<?php

declare(strict_types=1);

namespace yii\debug\tests\helpers;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\helpers\CellMore;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see CellMore} covering the collapsible clamp container, the verbatim body passthrough, and the
 * toggle wiring (delegation attribute, collapsed ARIA state, label).
 */
#[Group('helpers')]
#[Group('cell-more')]
final class CellMoreTest extends TestCase
{
    public function testWrapEmitsCollapsedToggleWiredForDelegation(): void
    {
        $html = CellMore::wrap('body');

        self::assertStringContainsString(
            '<button',
            $html,
            'Disclosure control must be a native button.',
        );
        self::assertStringContainsString(
            'type="button"',
            $html,
            'Button must not submit surrounding forms.',
        );
        self::assertStringNotContainsString(
            'javascript:',
            $html,
            'No `javascript:` URI may reach the markup.',
        );
        self::assertStringContainsString(
            'data-yii-debug-toggle="cell-more"',
            $html,
            'Toggle must carry the delegation attribute.',
        );
        self::assertStringContainsString(
            'aria-expanded="false"',
            $html,
            'Initial state must be collapsed.',
        );
        self::assertStringContainsString(
            '[+] Show more',
            $html,
            'Collapsed label must invite expansion.',
        );
    }

    public function testWrapKeepsBodyContentVerbatimInsideClampContainer(): void
    {
        $html = CellMore::wrap('<div class="yii-debug-db-sql">SELECT 1</div>');

        self::assertStringContainsString(
            'yii-debug-cell-more-body',
            $html,
            'Body container class must be present.',
        );
        self::assertStringContainsString(
            '<div class="yii-debug-db-sql">SELECT 1</div>',
            $html,
            'Content must pass through unescaped.',
        );
        self::assertStringContainsString(
            'yii-debug-cell-more',
            $html,
            'Clamp container class must be present.',
        );
    }
}
