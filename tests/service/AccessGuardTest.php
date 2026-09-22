<?php

declare(strict_types=1);

namespace yii\debug\tests\service;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\base\{Action, Controller};
use yii\debug\Module;
use yii\debug\service\AccessGuard;
use yii\debug\tests\support\ModuleTestCase;
use yii\log\Dispatcher;

use function gethostbyname;
use function is_array;
use function is_string;

/**
 * Unit tests for {@see AccessGuard} deciding whether a request may reach the debugger.
 */
#[Group('service')]
final class AccessGuardTest extends ModuleTestCase
{
    public function testAllowsGrantsWhenTheCallbackApproves(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];
        $module->checkAccessCallback = static fn(): bool => true;

        self::assertTrue(
            (new AccessGuard($module))->allows('127.0.0.1'),
            'An approving callback must keep the grant.',
        );
    }

    public function testAllowsHandsTheDispatchedActionToTheCallback(): void
    {
        $module = new Module('debug');
        $action = new Action('view', new Controller('default', $module));

        $received = null;

        $module->allowedIPs = ['*'];
        $module->checkAccessCallback = static function (Action|null $candidate) use (&$received): bool {
            $received = $candidate;

            return true;
        };

        (new AccessGuard($module))->allows('127.0.0.1', $action);

        self::assertSame(
            $action,
            $received,
            'Callback must receive the dispatched action.',
        );
    }

    public function testAllowsKeepsTheCallbackUnusedWhenTheAddressIsRejected(): void
    {
        $module = new Module('debug');

        $called = false;

        $module->allowedIPs = ['10.0.0.1'];
        $module->disableIpRestrictionWarning = true;
        $module->checkAccessCallback = static function () use (&$called): bool {
            $called = true;

            return true;
        };

        self::assertFalse(
            (new AccessGuard($module))->allows('127.0.0.1'),
            'Rejected address must deny access.',
        );
        self::assertFalse(
            $called,
            'Callback must stay unused.',
        );
    }

    public function testAllowsMatchesAnAllowedHostThroughDnsResolution(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = [];
        $module->allowedHosts = ['localhost'];

        self::assertTrue(
            (new AccessGuard($module))->allows(gethostbyname('localhost')),
            'Resolved host address must grant access.',
        );
    }

    public function testAllowsReadsTheCurrentModuleConfiguration(): void
    {
        $module = new Module('debug');
        $guard = new AccessGuard($module);

        $module->allowedIPs = ['*'];

        self::assertTrue(
            $guard->allows('127.0.0.1'),
            'The initial allowlist must grant access.',
        );

        $module->allowedIPs = [];
        $module->disableIpRestrictionWarning = true;

        self::assertFalse(
            $guard->allows('127.0.0.1'),
            'A later allowlist update must take effect on the next check.',
        );
    }

    public function testAllowsSuppressesTheCallbackWarningWhenDisabled(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];
        $module->disableCallbackRestrictionWarning = true;
        $module->checkAccessCallback = static fn(): bool => false;

        $this->captureLoggedMessages();

        self::assertFalse(
            (new AccessGuard($module))->allows('127.0.0.1'),
            'A rejecting callback must deny access.',
        );
        self::assertSame(
            '',
            $this->collectLoggedMessages(),
            'Callback denial must stay silent.',
        );
    }

    public function testAllowsSuppressesTheIpWarningWhenDisabled(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['10.0.0.1'];
        $module->disableIpRestrictionWarning = true;

        $this->captureLoggedMessages();

        self::assertFalse(
            (new AccessGuard($module))->allows('127.0.0.1'),
            'Unlisted address must be rejected.',
        );
        self::assertSame(
            '',
            $this->collectLoggedMessages(),
            'Address denial must stay silent.',
        );
    }

    public function testAllowsWarnsWithTheExactMessageWhenTheCallbackDenies(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];
        $module->checkAccessCallback = static fn(): bool => false;

        $this->captureLoggedMessages();

        self::assertFalse(
            (new AccessGuard($module))->allows('127.0.0.1'),
            'A rejecting callback must deny access.',
        );
        self::assertSame(
            'Access to debugger is denied due to checkAccessCallback.',
            $this->collectLoggedMessages(),
            'Denial must be reported verbatim.',
        );
    }

    public function testAllowsWarnsWithTheExactMessageWhenTheIpIsRejected(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['10.0.0.1'];

        $this->captureLoggedMessages();

        self::assertFalse(
            (new AccessGuard($module))->allows('127.0.0.1'),
            'Unlisted address must be rejected.',
        );
        self::assertSame(
            'Access to debugger is denied due to IP address restriction. The requesting IP address is 127.0.0.1',
            $this->collectLoggedMessages(),
            'Denial must name the requesting address.',
        );
    }

    /**
     * Swaps the log dispatcher for a stub so warnings accumulate in the logger instead of being flushed.
     */
    private function captureLoggedMessages(): void
    {
        Yii::getLogger()->dispatcher = self::createStub(Dispatcher::class);
        Yii::getLogger()->messages = [];
    }

    /**
     * Concatenates the accumulated logger message bodies.
     */
    private function collectLoggedMessages(): string
    {
        $messages = $this->getInaccessibleProperty(Yii::getLogger(), 'messages');

        $out = '';

        if (is_iterable($messages)) {
            foreach ($messages as $message) {
                if (is_array($message) && isset($message[0]) && is_string($message[0])) {
                    $out .= $message[0];
                }
            }
        }

        return $out;
    }
}
