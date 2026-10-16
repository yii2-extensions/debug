<?php

declare(strict_types=1);

namespace yii\debug\tests\actions;

use PHPUnit\Framework\Attributes\{Group, TestWith};
use Throwable;
use Yii;
use yii\debug\{Module, Panel};
use yii\debug\panels\UserPanel;
use yii\debug\tests\support\stub\Identity;
use yii\debug\tests\support\TestCase;
use yii\web\{BadRequestHttpException, ForbiddenHttpException, MethodNotAllowedHttpException, User};

use function session_name;

/**
 * Tests identity authorization through Yii's application routing and debugger bootstrap hooks.
 */
#[Group('actions')]
#[Group('user')]
final class IdentityAuthorizationTest extends TestCase
{
    public function testAuthorizedRoutePreservesAnAlternateUserComponent(): void
    {
        $panel = $this->allowedPanel();

        $panel['userComponent'] = 'backendUser';

        $this->bootRoute($panel);

        $backend = Yii::$app->get('backendUser');

        self::assertInstanceOf(
            User::class,
            $backend,
            'The alternate component must resolve to a user.',
        );

        $backend->login(new Identity(7));

        $this->refreshPost();

        Yii::$app->runAction('debug/set-identity', ['user' => $backend]);

        self::assertSame(
            42,
            $backend->getId(),
            'The selected user component must receive the new identity.',
        );
        self::assertSame(
            1,
            Yii::$app->user->getId(),
            'Switching another component must preserve the default user.',
        );

        $this->refreshPost();

        Yii::$app->runAction('debug/reset-identity', ['user' => $backend]);

        self::assertSame(
            7,
            $backend->getId(),
            'Reset must restore the alternate component.',
        );
    }

    /**
     * @param class-string<Throwable> $exception Expected routing guard exception.
     */
    #[TestWith(['GET', '127.0.0.1', 'valid', MethodNotAllowedHttpException::class])]
    #[TestWith(['POST', '127.0.0.1', 'invalid', BadRequestHttpException::class])]
    #[TestWith(['POST', '127.0.0.1', 'inactive', BadRequestHttpException::class])]
    #[TestWith(['POST', '192.0.2.99', 'valid', ForbiddenHttpException::class])]
    public function testAuthorizedRoutesRetainRequestGuards(
        string $method,
        string $ip,
        string $session,
        string $exception,
    ): void {
        $this->bootRoute($this->allowedPanel(), $ip);

        $_SERVER['REQUEST_METHOD'] = $method;

        if ($session === 'invalid') {
            Yii::$app->request->setBodyParams(['user_id' => 42, Yii::$app->request->csrfParam => 'invalid']);
        } elseif ($session === 'inactive') {
            Yii::$app->session->setHasSessionId(false);
        }

        $this->expectException($exception);

        try {
            Yii::$app->runAction('debug/set-identity');
        } finally {
            self::assertSame(
                1,
                Yii::$app->user->getId(),
                'A rejected request must preserve the identity.',
            );
        }
    }

    public function testAuthorizedRoutesSwitchAndRestoreTheOriginalIdentity(): void
    {
        $this->bootRoute($this->allowedPanel());

        Yii::$app->runAction('debug/set-identity');

        self::assertSame(
            42,
            Yii::$app->user->getId(),
            'An explicitly authorized route must switch the identity.',
        );

        $this->refreshPost();

        Yii::$app->runAction('debug/reset-identity');

        self::assertSame(
            1,
            Yii::$app->user->getId(),
            'Reset must restore the identity saved before switching.',
        );
    }

    #[TestWith(['set-identity'])]
    #[TestWith(['reset-identity'])]
    public function testThrowForbiddenHttpExceptionWhenDefaultSwitchRuleDeniesAccess(string $action): void
    {
        $this->bootRoute(['class' => UserPanel::class]);

        $this->expectException(ForbiddenHttpException::class);

        Yii::$app->runAction("debug/{$action}");
    }

    #[TestWith(['set-identity', 'disabled'])]
    #[TestWith(['reset-identity', 'disabled'])]
    #[TestWith(['set-identity', 'unavailable'])]
    #[TestWith(['reset-identity', 'unavailable'])]
    #[TestWith(['set-identity', 'replacement'])]
    #[TestWith(['reset-identity', 'replacement'])]
    public function testThrowForbiddenHttpExceptionWhenUserSwitchAuthorizationIsAbsent(string $action, string $mode): void
    {
        $panel = match ($mode) {
            'disabled' => ['enabled' => false],
            'unavailable' => ['class' => UserPanel::class, 'userComponent' => 'missing-user'],
            default => ['class' => Panel::class],
        };

        $this->bootRoute($panel);

        Yii::$app->session->set('main_user', 7);

        $this->expectException(ForbiddenHttpException::class);

        try {
            Yii::$app->runAction("debug/{$action}");
        } finally {
            self::assertSame(
                1,
                Yii::$app->user->getId(),
                'A denied identity action must preserve the active user.',
            );
            self::assertSame(
                7,
                Yii::$app->session->get('main_user'),
                'A denied action must preserve reset state.',
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
    }

    /**
     * @return array<string, mixed> User panel with an explicit switch permission.
     */
    private function allowedPanel(): array
    {
        return ['class' => UserPanel::class, 'ruleUserSwitch' => ['allow' => true]];
    }

    /**
     * @param array<string, mixed> $panel User-panel configuration.
     */
    private function bootRoute(array $panel, string $ip = '127.0.0.1'): void
    {
        $_SERVER['REMOTE_ADDR'] = $ip;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $sessionName = session_name();

        self::assertIsString(
            $sessionName,
            'The fixture session must have a name before application bootstrap.',
        );

        $_COOKIE[$sessionName] = 'identity-authorization-test';

        $user = ['class' => User::class, 'identityClass' => Identity::class, 'enableAutoLogin' => false];

        $this->mockWebApplication(
            [
                'bootstrap' => ['debug'],
                'modules' => ['debug' => ['class' => Module::class, 'panels' => ['user' => $panel]]],
                'components' => ['user' => $user, 'backendUser' => $user],
            ],
        );

        $module = Yii::$app->getModule('debug');

        self::assertInstanceOf(
            Module::class,
            $module,
            'The debugger must bootstrap before routing.',
        );

        $module->getLogTarget()->enabled = false;

        Yii::$app->session->open();
        Yii::$app->user->login(new Identity(1));

        $this->refreshPost();
    }

    private function refreshPost(): void
    {
        $request = Yii::$app->request;

        $request->setBodyParams(['user_id' => 42, $request->csrfParam => $request->getCsrfToken()]);
    }
}
