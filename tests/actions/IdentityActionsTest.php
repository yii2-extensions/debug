<?php

declare(strict_types=1);

namespace yii\debug\tests\actions;

use PHPUnit\Framework\Attributes\Group;
use stdClass;
use Yii;
use yii\debug\actions\{ResetIdentityAction, SetIdentityAction};
use yii\debug\exception\Message;
use yii\debug\models\UserSwitch;
use yii\debug\Module;
use yii\debug\tests\support\stub\{Identity, NullableIdentity};
use yii\debug\tests\support\TestCase;
use yii\web\{BadRequestHttpException, MethodNotAllowedHttpException, Response, User};

/**
 * Unit tests for {@see SetIdentityAction} and {@see ResetIdentityAction} covering the identity swap happy path, the
 * `BadRequestHttpException` paths (missing/invalid `user_id`, missing identity class, identity not found), and the
 * `beforeRun` JSON format + active-session guard.
 */
#[Group('actions')]
#[Group('user')]
final class IdentityActionsTest extends TestCase
{
    public function testActionResetIdentityRestoresOriginalUser(): void
    {
        $this->bootApp();

        $primary = Yii::$app->user;

        $primary->setIdentity(new Identity(99));

        $secondary = $this->newUser();

        $secondary->setIdentity(new Identity(42));

        Yii::$app->session->set('main_user', 7);

        self::assertSame(
            42,
            $secondary->getId(),
            'The fixture must impersonate a different identity before reset.',
        );

        $result = (new ResetIdentityAction('reset-identity'))->run($secondary);

        self::assertSame(
            $secondary,
            $result,
            'Reset must return the user component it operated on.',
        );
        self::assertFalse(
            $result->isGuest,
            'Reset must leave an authenticated identity in place.',
        );
        self::assertSame(
            7,
            $result->getId(),
            'Reset must restore the original identity captured before impersonation.',
        );
        self::assertSame(
            99,
            $primary->getId(),
            'Reset must not operate on the default application user.',
        );
    }

    public function testActionSetIdentitySwitchesActiveUserToPostedUserId(): void
    {
        $this->bootApp();

        $primary = Yii::$app->user;

        $primary->setIdentity(new Identity(1));

        $secondary = $this->newUser();

        $secondary->setIdentity(new Identity(7));

        Yii::$app->request->setBodyParams(['user_id' => 42]);

        $result = (new SetIdentityAction('set-identity'))->run($secondary, Yii::$app->request);

        $identity = $result->identity;

        self::assertInstanceOf(
            Identity::class,
            $identity,
            'Identity must be swapped to the resolved fixture.',
        );
        self::assertSame(
            42,
            $identity->getId(),
            "Resolved identity id must match the posted 'user_id'.",
        );
        self::assertSame(
            $secondary,
            $result,
            'Set identity must return the user component it operated on.',
        );
        self::assertSame(
            1,
            $primary->getId(),
            'Set identity must not operate on the default application user.',
        );
    }

    public function testBeforeRunForcesJsonResponseFormat(): void
    {
        $this->bootApp();

        Yii::$app->session->open();
        Yii::$app->user->login(new Identity(7));

        (new UserSwitch())->setUserByIdentity(new Identity(42));

        $this->prepareValidPost();

        (new ResetIdentityAction('reset-identity'))->runWithParams(['user' => Yii::$app->user]);

        self::assertSame(
            Response::FORMAT_JSON,
            Yii::$app->response->format,
            'JSON response format must be enforced.',
        );
    }

    public function testRunWithParamsResolvesUserAndRequestByComponentName(): void
    {
        $this->bootApp();

        Yii::$app->user->login(new Identity(1));

        $this->prepareValidPost(['user_id' => 42]);

        $module = new Module('debug', Yii::$app);
        $action = new SetIdentityAction('set-identity');

        $action->setModule($module);

        $result = $action->runWithParams([]);

        self::assertInstanceOf(
            User::class,
            $result,
            'Bound components must yield the user component.',
        );
        self::assertSame(
            42,
            $result->getId(),
            'Identity must be swapped to the posted id.',
        );
    }

    public function testThrowBadRequestHttpExceptionWhenCsrfTokenIsInvalid(): void
    {
        $this->bootApp();

        Yii::$app->user->login(new Identity(1));

        $_SERVER['REQUEST_METHOD'] = 'POST';

        Yii::$app->request->setBodyParams(['user_id' => 42, Yii::$app->request->csrfParam => 'invalid']);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(
            Message::CSRF_VALIDATION_FAILED->getMessage(),
        );

        (new SetIdentityAction('set-identity'))->runWithParams(
            ['user' => Yii::$app->user, 'request' => Yii::$app->request],
        );
    }

    public function testThrowBadRequestHttpExceptionWhenCsrfTokenIsMissing(): void
    {
        $this->bootApp();

        Yii::$app->user->login(new Identity(1));

        $_SERVER['REQUEST_METHOD'] = 'POST';

        Yii::$app->request->setBodyParams(['user_id' => 42]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(
            Message::CSRF_VALIDATION_FAILED->getMessage(),
        );

        (new SetIdentityAction('set-identity'))->runWithParams(
            ['user' => Yii::$app->user, 'request' => Yii::$app->request],
        );
    }

    public function testThrowBadRequestHttpExceptionWhenIdentityCannotBeResolved(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'user' => [
                        'class' => User::class,
                        'identityClass' => NullableIdentity::class,
                        'enableSession' => true,
                        'enableAutoLogin' => false,
                    ],
                ],
            ],
        );

        Yii::$app->request->setBodyParams(['user_id' => -1]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(
            Message::IDENTITY_NOT_FOUND->getMessage(),
        );

        (new SetIdentityAction('set-identity'))->run(Yii::$app->user, Yii::$app->request);
    }

    public function testThrowBadRequestHttpExceptionWhenIdentityClassIsNotConfigured(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'user' => [
                        'class' => User::class,
                        'identityClass' => stdClass::class,
                        'enableSession' => true,
                        'enableAutoLogin' => false,
                    ],
                ],
            ],
        );

        Yii::$app->request->setBodyParams(['user_id' => 1]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(
            Message::IDENTITY_CLASS_NOT_CONFIGURED->getMessage(),
        );

        (new SetIdentityAction('set-identity'))->run(Yii::$app->user, Yii::$app->request);
    }

    public function testThrowBadRequestHttpExceptionWhenSessionIsInactive(): void
    {
        $this->bootApp();

        $_SERVER['REQUEST_METHOD'] = 'POST';

        // Drop the session id so `hasSessionId` reports `false`.
        unset($_COOKIE[Yii::$app->session->getName()]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(
            Message::ACTIVE_SESSION_REQUIRED->getMessage(),
        );

        (new ResetIdentityAction('reset-identity'))->runWithParams(['user' => Yii::$app->user]);
    }

    public function testThrowBadRequestHttpExceptionWhenUserIdIsNotScalar(): void
    {
        $this->bootApp();

        Yii::$app->request->setBodyParams(['user_id' => ['not-scalar']]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(
            Message::USER_ID_INVALID->getMessage(),
        );

        (new SetIdentityAction('set-identity'))->run(Yii::$app->user, Yii::$app->request);
    }

    public function testThrowMethodNotAllowedHttpExceptionWhenRequestIsNotPost(): void
    {
        $this->bootApp();

        Yii::$app->user->login(new Identity(1));

        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->expectException(MethodNotAllowedHttpException::class);
        $this->expectExceptionMessage(
            Message::POST_ONLY->getMessage(),
        );

        (new SetIdentityAction('set-identity'))->runWithParams(
            ['user' => Yii::$app->user, 'request' => Yii::$app->request],
        );
    }

    private function bootApp(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'user' => [
                        'class' => User::class,
                        'identityClass' => Identity::class,
                        'enableSession' => true,
                        'enableAutoLogin' => false,
                    ],
                ],
            ],
        );

        // Force `hasSessionId === true` by seeding the session cookie before the test body opens it.
        $_COOKIE[Yii::$app->session->getName()] = 'test-session-id';
    }

    private function newUser(): User
    {
        return new User(
            [
                'identityClass' => Identity::class,
                'enableSession' => true,
                'enableAutoLogin' => false,
            ],
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function prepareValidPost(array $body = []): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $request = Yii::$app->request;
        $body[$request->csrfParam] = $request->getCsrfToken();

        $request->setBodyParams($body);
    }
}
