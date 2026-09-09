<?php

declare(strict_types=1);

namespace yii\debug\tests\module;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\Module;
use yii\debug\tests\support\ModuleTestCase;
use yii\web\ErrorHandlerRenderEvent;

/**
 * Unit tests for {@see Module} covering `injectToolbarOnErrorPage` insertion before the closing body tag, append-only
 * output without a body marker, runtime script ordering, and the AJAX short-circuit.
 */
#[Group('module')]
final class ModuleErrorToolbarTest extends ModuleTestCase
{
    public function testInjectToolbarOnErrorPageAppendsWhenBodyMarkerMissing(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        $module->bootstrap(Yii::$app);

        $this->silenceLogger();

        $event = new ErrorHandlerRenderEvent();

        $event->output = 'plain text error';

        $module->injectToolbarOnErrorPage($event);

        self::assertStringContainsString(
            'plain text error',
            $event->output,
            'Original output must be preserved.',
        );
        self::assertStringContainsString(
            '<yii-debug-toolbar',
            $event->output,
            'Toolbar markup must be appended when no closing body marker exists.',
        );
    }

    public function testInjectToolbarOnErrorPageReplacesClosingBodyMarker(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        $module->bootstrap(Yii::$app);

        $this->silenceLogger();

        $event = new ErrorHandlerRenderEvent();

        $event->output = '<html><body>boom</body></html>';

        $module->injectToolbarOnErrorPage($event);

        self::assertStringContainsString(
            '<yii-debug-toolbar',
            $event->output,
            'Toolbar markup must precede the closing body marker.',
        );
        self::assertStringContainsString(
            '<script type="module"',
            $event->output,
            'Runtime script must load as an ES module.',
        );
        self::assertTrue(
            strpos($event->output, '<yii-debug-toolbar') < strpos($event->output, '<script type="module"'),
            'Toolbar markup must precede its module script.',
        );
    }

    public function testInjectToolbarOnErrorPageShortCircuitsOnAjaxRequests(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        $module->bootstrap(Yii::$app);

        $event = new ErrorHandlerRenderEvent();

        $event->output = '<body></body>';

        $module->injectToolbarOnErrorPage($event);

        self::assertSame(
            '<body></body>',
            $event->output,
            'AJAX requests must leave the rendered error page untouched.',
        );

        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }
}
