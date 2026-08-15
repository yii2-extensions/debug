<?php

declare(strict_types=1);

namespace yii\debug\tests\widgets;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\Tabs;

/**
 * Unit tests for {@see Tabs} covering the accessible active and inactive tab states.
 */
#[Group('widget')]
#[Group('tabs')]
final class TabsTest extends TestCase
{
    public function testRenderMarksOnlyTheFirstTabAndPanelAsActive(): void
    {
        $html = Tabs::render(
            'example',
            'Example tabs',
            [
                ['label' => 'First', 'content' => '<p>One</p>'],
                ['label' => 'Second', 'content' => '<p>Two</p>'],
            ],
        );

        self::assertStringContainsString(
            '<a class="yii-debug-tab-link is-active" id="example-tab-0" href="#example-panel-0" role="tab" '
            . 'tabindex="0" aria-controls="example-panel-0" aria-selected="true" data-yii-debug-toggle="tab">First</a>',
            $html,
            'First tab must be selected, active, and keyboard-focusable.',
        );
        self::assertStringContainsString(
            '<a class="yii-debug-tab-link" id="example-tab-1" href="#example-panel-1" role="tab" tabindex="-1" '
            . 'aria-controls="example-panel-1" aria-selected="false" data-yii-debug-toggle="tab">Second</a>',
            $html,
            'Inactive tab must be unselected and removed from the initial tab order.',
        );
        self::assertStringContainsString(
            '<div class="yii-debug-tab-panel is-active" id="example-panel-0" role="tabpanel" '
            . 'aria-labelledby="example-tab-0">',
            $html,
            'First panel must be active and visible.',
        );
        self::assertStringContainsString(
            '<div class="yii-debug-tab-panel" id="example-panel-1" role="tabpanel" '
            . 'aria-labelledby="example-tab-1" hidden>',
            $html,
            'Inactive panel must remain hidden until its tab is selected.',
        );
    }
}
