<?php

declare(strict_types=1);

namespace yii\debug\tests\module;

use PHPUnit\Framework\Attributes\Group;
use stdClass;
use Xepozz\InternalMocker\MockerState;
use Yii;
use yii\base\{Action, Controller, Event};
use yii\debug\{LogTarget, Module};
use yii\debug\tests\support\ModuleTestCase;
use yii\web\Response;

/**
 * Unit tests for {@see Module} covering response security headers, host CSP composition, the response-header extension
 * point, `setDebugHeaders` tag/duration/link values, and access-denied or invalid-sender short-circuits.
 */
#[Group('module')]
final class ModuleResponseHeadersTest extends ModuleTestCase
{
    public function testBeforeActionComposesDebuggerFramePolicyAcrossHostCspValues(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        Yii::$app->setModule(
            'debug',
            $module,
        );

        $response = Yii::$app->getResponse();

        $headers = $response->getHeaders();

        $headers->set(
            'Content-Security-Policy',
            "default-src 'none'; ; img-src data:;",
        );
        $headers->add(
            'Content-Security-Policy',
            "script-src 'self'; FRAME-ANCESTORS https://example.test; style-src 'unsafe-inline'",
        );
        $headers->add('Content-Security-Policy', 'img-src frame-ancestors');

        $action = new Action('index', new Controller('default', $module));

        self::assertTrue(
            $module->beforeAction($action),
            'An allowed debugger action must continue after composing host policies.',
        );
        self::assertSame(
            [
                "default-src 'none'; img-src data:; frame-ancestors 'self'",
                "script-src 'self'; frame-ancestors 'self'; style-src 'unsafe-inline'",
                "img-src frame-ancestors; frame-ancestors 'self'",
            ],
            $headers->get('Content-Security-Policy', null, false),
            'Every host CSP value, including directives after empty segments, must remain while enforcing framing.',
        );
    }

    public function testBeforeActionHardensDebuggerResponsesWithoutBlockingSameOriginFrames(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        Yii::$app->setModule(
            'debug',
            $module,
        );

        $response = Yii::$app->getResponse();

        $response->getHeaders()->set(
            'Content-Security-Policy',
            "default-src 'none'; frame-ancestors 'none'",
        );

        $action = new Action('index', new Controller('default', $module));

        self::assertTrue(
            $module->beforeAction($action),
            'An allowed debugger action must continue after response hardening.',
        );
        self::assertSame(
            'no-store, no-cache, must-revalidate, max-age=0',
            $response->getHeaders()->get('Cache-Control'),
            'Debugger responses must never be stored by browsers or intermediaries.',
        );
        self::assertSame(
            'no-cache',
            $response->getHeaders()->get('Pragma'),
            'HTTP/1.0 caches must receive the matching no-cache directive.',
        );
        self::assertSame(
            'no-referrer',
            $response->getHeaders()->get('Referrer-Policy'),
            'Captured tags and filter values must not leak through outbound referrers.',
        );
        self::assertSame(
            'nosniff',
            $response->getHeaders()->get('X-Content-Type-Options'),
            'Debugger assets and downloads must opt out of content sniffing.',
        );
        self::assertSame(
            'noindex, nofollow, noarchive',
            $response->getHeaders()->get('X-Robots-Tag'),
            'Debugger endpoints must not be indexed or archived.',
        );
        self::assertSame(
            'SAMEORIGIN',
            $response->getHeaders()->get('X-Frame-Options'),
            'Only the same-origin toolbar drawer may frame debugger pages.',
        );
        self::assertSame(
            "default-src 'none'; frame-ancestors 'self'",
            $response->getHeaders()->get('Content-Security-Policy'),
            'The debugger CSP must preserve host directives while replacing only an incompatible frame policy.',
        );
    }

    public function testBeforeActionUsesTheResponseHeaderExtensionPoint(): void
    {
        $module = new class ('debug') extends Module {
            public bool $responseHeadersApplied = false;

            protected function setDebuggerResponseHeaders(Response $response): void
            {
                $this->responseHeadersApplied = true;

                parent::setDebuggerResponseHeaders($response);
            }
        };

        $module->allowedIPs = ['*'];

        Yii::$app->setModule(
            'debug',
            $module,
        );

        self::assertTrue(
            $module->beforeAction(new Action('index', new Controller('default', $module))),
            'An allowed debugger action must continue.',
        );
        self::assertTrue(
            $module->responseHeadersApplied,
            'The protected response-header extension point must participate in the action lifecycle.',
        );
    }

    public function testSetDebugHeadersAppliesAllThreeHeaders(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        $module->bootstrap(Yii::$app);

        $this->silenceLogger();

        $_SERVER['REQUEST_TIME_FLOAT'] = 1000.0;

        MockerState::addCondition(
            'yii\\debug',
            'microtime',
            [true],
            1000.5,
            true,
        );

        $response = Yii::$app->getResponse();

        $module->setDebugHeaders(new Event(['sender' => $response]));

        $headers = $response->getHeaders();

        self::assertInstanceOf(
            LogTarget::class,
            $module->logTarget,
            'Bootstrap must resolve the log target.',
        );
        self::assertSame(
            [
                'tag' => $module->logTarget->tag,
                'duration' => '500.000',
                'link' => \yii\helpers\Url::toRoute(['/debug/view', 'tag' => $module->logTarget->tag]),
            ],
            [
                'tag' => $headers->get('X-Debug-Tag'),
                'duration' => $headers->get('X-Debug-Duration'),
                'link' => $headers->get('X-Debug-Link'),
            ],
            'Debug headers must contain exact tag, duration, and link values.',
        );
    }

    public function testSetDebugHeadersSkipsWhenAccessDenied(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['10.0.0.0'];
        $module->disableIpRestrictionWarning = true;

        $module->bootstrap(Yii::$app);

        $response = Yii::$app->getResponse();

        $module->setDebugHeaders(new Event(['sender' => $response]));

        self::assertFalse(
            $response->getHeaders()->has('X-Debug-Tag'),
            'Access denial must skip the debug-header injection.',
        );
    }

    public function testSetDebugHeadersSkipsWhenSenderIsNotResponse(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        $module->bootstrap(Yii::$app);

        $this->silenceLogger();

        $module->setDebugHeaders(new Event(['sender' => new stdClass()]));

        self::assertFalse(
            Yii::$app->getResponse()->getHeaders()->has('X-Debug-Tag'),
            'Non-Response senders must leave headers untouched.',
        );
    }
}
