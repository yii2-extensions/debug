<?php

declare(strict_types=1);

namespace yii\debug\tests\module;

use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use Yii;
use yii\base\{Action, Controller};
use yii\debug\exception\Message;
use yii\debug\Module;
use yii\debug\tests\provider\ModuleProvider;
use yii\debug\tests\support\ModuleTestCase;
use yii\log\Dispatcher;
use yii\web\ForbiddenHttpException;

use function is_array;
use function is_string;

/**
 * Unit tests for {@see Module} covering `checkAccess` callbacks, IP/CIDR and hostname allowlists, denial warnings,
 * runtime allowlist changes, and action-specific access-denial handling.
 *
 * {@see ModuleProvider} for test case data providers.
 */
#[Group('module')]
final class ModuleAccessTest extends ModuleTestCase
{
    public function testBeforeActionReturnsFalseForToolbarDataRouteUnderAccessDenial(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['10.0.0.1'];
        $module->disableIpRestrictionWarning = true;

        Yii::$app->setModule(
            'debug',
            $module,
        );

        $action = new Action('toolbar-data', new Controller('default', $module));

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        self::assertFalse(
            $module->beforeAction($action),
            "Denied access to the 'toolbar-data' action must return 'false' instead of throwing.",
        );
    }

    public function testCheckAccessAppliesCallbackBeforeGrantingAccess(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];
        $module->disableCallbackRestrictionWarning = true;
        $module->checkAccessCallback = static fn(): bool => false;

        self::assertFalse(
            $this->invoke($module, 'checkAccess'),
            "Returning anything other than 'true' must deny access.",
        );
    }

    public function testCheckAccessEmitsWarningWhenCallbackDeniesWithWarningEnabled(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];
        $module->disableCallbackRestrictionWarning = false;
        $module->checkAccessCallback = static fn(): bool => false;

        Yii::getLogger()->dispatcher = self::createStub(Dispatcher::class);
        Yii::getLogger()->messages = [];

        self::assertFalse(
            $this->invoke($module, 'checkAccess'),
            "Callback denying access must return 'false'.",
        );
        self::assertStringContainsString(
            'Access to debugger is denied due to checkAccessCallback.',
            $this->collectLoggedMessages(),
            'Callback denial must be logged when warnings are enabled.',
        );
    }

    public function testCheckAccessGrantsWhenCallbackApproves(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];
        $module->checkAccessCallback = static fn(): bool => true;

        self::assertTrue(
            $this->invoke($module, 'checkAccess'),
            "Returning 'true' must grant access after the IP filter passes.",
        );
    }

    /**
     * @param list<string> $allowedIPs
     */
    #[DataProviderExternal(ModuleProvider::class, 'checkAccessCases')]
    public function testCheckAccessHonorsAllowedIpAndCidrFilters(
        array $allowedIPs,
        string $userIp,
        bool $expectedResult,
    ): void {
        $module = new Module('debug');

        $module->allowedIPs = $allowedIPs;

        $_SERVER['REMOTE_ADDR'] = $userIp;

        self::assertSame(
            $expectedResult,
            $this->invoke(
                $module,
                'checkAccess',
            ),
            'Allowed IP filters must accept matches and reject non-matching addresses.',
        );
    }

    public function testCheckAccessLogsExactIpRestrictionWarning(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['10.0.0.1'];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        Yii::getLogger()->dispatcher = self::createStub(Dispatcher::class);
        Yii::getLogger()->messages = [];

        self::assertFalse(
            $this->invoke($module, 'checkAccess'),
            'Unlisted IP must be rejected.',
        );
        self::assertSame(
            'Access to debugger is denied due to IP address restriction. The requesting IP address is 127.0.0.1',
            $this->collectLoggedMessages(),
            'Denied IP access must emit the exact warning.',
        );
    }

    public function testCheckAccessMatchesAllowedHostsViaDnsResolution(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = [];
        $module->allowedHosts = ['localhost'];

        $_SERVER['REMOTE_ADDR'] = gethostbyname('localhost');

        self::assertTrue(
            $this->invoke($module, 'checkAccess'),
            'Must be resolved via DNS and matched against the requester IP.',
        );
    }

    public function testCheckAccessUsesTheCurrentPublicAllowlistConfiguration(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        self::assertTrue(
            $this->invoke($module, 'checkAccess'),
            'The initial allowlist must grant access.',
        );

        $module->allowedIPs = [];
        $module->disableIpRestrictionWarning = true;

        self::assertFalse(
            $this->invoke($module, 'checkAccess'),
            'A later public allowlist update must take effect on the next access check.',
        );
    }

    public function testThrowForbiddenHttpExceptionForRemovedToolbarActionUnderAccessDenial(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['10.0.0.1'];
        $module->disableIpRestrictionWarning = true;

        Yii::$app->setModule(
            'debug',
            $module,
        );

        $action = new Action('toolbar', new Controller('default', $module));

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->expectException(ForbiddenHttpException::class);
        $this->expectExceptionMessage(
            Message::ACCESS_DENIED->getMessage(),
        );

        $module->beforeAction($action);
    }

    public function testThrowForbiddenHttpExceptionWhenAccessDeniedOnNonToolbarAction(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['10.0.0.0'];
        $module->disableIpRestrictionWarning = true;

        Yii::$app->setModule(
            'debug',
            $module,
        );

        $action = new Action('view', new Controller('default', $module));

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->expectException(ForbiddenHttpException::class);
        $this->expectExceptionMessage(
            Message::ACCESS_DENIED->getMessage(),
        );

        $module->beforeAction($action);
    }

    /**
     * Concatenates the active logger message bodies for access-denial assertions.
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
