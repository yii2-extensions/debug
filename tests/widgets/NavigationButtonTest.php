<?php

declare(strict_types=1);

namespace yii\debug\tests\widgets;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\{LogTarget, Module};
use yii\debug\panels\LogPanel;
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\NavigationButton;
use yii\web\Controller;

/**
 * Unit tests for {@see NavigationButton} covering the Prev/Next anchor rendering, manifest cursor resolution, and the
 * `is-disabled` modifier applied at the manifest edges.
 */
#[Group('widget')]
#[Group('navigation-button')]
final class NavigationButtonTest extends TestCase
{
    public function testGetRouteReturnsEmptyForOutOfBoundsIncrement(): void
    {
        $this->bootApp();

        $panel = new LogPanel();

        $panel->id = 'log';

        $widget = new NavigationButton(
            ['manifest' => ['only' => []], 'tag' => 'only', 'panel' => $panel],
        );

        $widget->beforeRun();

        self::assertSame(
            '',
            $this->invoke($widget, 'getRoute', [5]),
            "Out-of-bounds target index must surface as an empty route, exercising the defensive 'isset' guard.",
        );
    }
    public function testRenderEmitsEmptyMarkupForUnknownButtonKeyword(): void
    {
        $this->bootApp();

        self::assertSame(
            '',
            NavigationButton::widget(['button' => 'Unknown', 'manifest' => ['a' => []], 'tag' => 'a']),
            "Unknown 'button' values must collapse to an empty render.",
        );
    }

    public function testRenderNextButtonExposesActiveLinkForMiddleTag(): void
    {
        $this->bootApp();

        $panel = new LogPanel();

        $panel->id = 'log';

        $html = NavigationButton::widget(
            [
                'button' => NavigationButton::BUTTON_NEXT,
                'manifest' => ['newest' => [], 'middle' => [], 'oldest' => []],
                'tag' => 'middle',
                'panel' => $panel,
            ],
        );

        self::assertStringNotContainsString(
            'is-disabled',
            $html,
            "Middle-of-manifest tag must render an active 'Next' link without the 'is-disabled' modifier.",
        );
        self::assertStringContainsString(
            'tag=oldest',
            $html,
            "'Next' link must target the manifest entry after the current cursor.",
        );
        self::assertStringContainsString(
            '>Next<',
            $html,
            "'Next' anchor must surface the 'Next' label.",
        );
    }

    public function testRenderNextButtonIsDisabledAtLastTag(): void
    {
        $this->bootApp();

        $panel = new LogPanel();

        $panel->id = 'log';

        $html = NavigationButton::widget(
            [
                'button' => NavigationButton::BUTTON_NEXT,
                'manifest' => ['newest' => [], 'middle' => [], 'oldest' => []],
                'tag' => 'oldest',
                'panel' => $panel,
            ],
        );

        self::assertStringContainsString(
            'is-disabled',
            $html,
            "Last-tag cursor must mark the 'Next' button as 'is-disabled'.",
        );
    }

    public function testRenderPrevButtonExposesActiveLinkForMiddleTag(): void
    {
        $this->bootApp();

        $panel = new LogPanel();

        $panel->id = 'log';

        $html = NavigationButton::widget(
            [
                'button' => NavigationButton::BUTTON_PREV,
                'manifest' => ['newest' => [], 'middle' => [], 'oldest' => []],
                'tag' => 'middle',
                'panel' => $panel,
            ],
        );

        self::assertStringNotContainsString(
            'is-disabled',
            $html,
            "Middle-of-manifest tag must render an active 'Prev' link without the 'is-disabled' modifier.",
        );
        self::assertStringContainsString(
            'tag=newest',
            $html,
            "'Prev' link must target the manifest entry before the current cursor.",
        );
        self::assertStringContainsString(
            '>Prev<',
            $html,
            "'Prev' anchor must surface the 'Prev' label.",
        );
    }

    public function testRenderPrevButtonIsDisabledAtFirstTag(): void
    {
        $this->bootApp();

        $panel = new LogPanel();

        $panel->id = 'log';

        $html = NavigationButton::widget(
            [
                'button' => NavigationButton::BUTTON_PREV,
                'manifest' => ['newest' => [], 'middle' => [], 'oldest' => []],
                'tag' => 'newest',
                'panel' => $panel,
            ],
        );

        self::assertStringContainsString(
            'is-disabled',
            $html,
            "First-tag cursor must mark the 'Prev' button as 'is-disabled'.",
        );
    }

    public function testRouteResolvesToEmptyWhenCursorTagAbsentFromManifest(): void
    {
        $this->bootApp();

        $panel = new LogPanel();

        $panel->id = 'log';

        $html = NavigationButton::widget(
            [
                'button' => NavigationButton::BUTTON_NEXT,
                'manifest' => ['newest' => [], 'oldest' => []],
                'tag' => 'orphan-tag',
                'panel' => $panel,
            ],
        );

        self::assertStringNotContainsString(
            'tag=',
            $html,
            "Tags absent from the manifest must collapse the route to empty, dropping the 'tag=' query parameter.",
        );
    }

    public function testRouteResolvesToEmptyWhenPanelMissing(): void
    {
        $this->bootApp();

        $html = NavigationButton::widget(
            [
                'button' => NavigationButton::BUTTON_NEXT,
                'manifest' => ['only' => []],
                'tag' => 'only',
                'panel' => null,
            ],
        );

        self::assertStringContainsString(
            'is-disabled',
            $html,
            "Without a panel the 'Next' button must still surface as disabled.",
        );
    }

    private function bootApp(): void
    {
        $this->mockWebApplication();

        Yii::$app->getRequest()->setUrl('dummy');

        $module = new Module('debug');
        $module->logTarget = new LogTarget($module);

        Yii::$app->controller = new Controller('debug', $module);
    }
}
