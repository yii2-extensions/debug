<?php

declare(strict_types=1);

namespace yii\debug\tests\controllers;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\base\Module;
use yii\debug\controllers\UserController;
use yii\debug\tests\support\stub\{Identity, NullableIdentity};
use yii\debug\tests\support\TestCase;
use yii\web\{BadRequestHttpException, Response, User};

/**
 * Unit tests for {@see UserController} covering `actionSetIdentity` happy path, the three `BadRequestHttpException`
 * paths (missing/invalid `user_id`, missing identity class, identity not found), `actionResetIdentity`, and the
 * `beforeAction` JSON format + active-session guard.
 */
#[Group('controllers')]
#[Group('user')]
final class UserControllerTest extends TestCase
{
    public function testActionResetIdentityRestoresOriginalUser(): void
    {
        $this->bootApp();

        Yii::$app->user->login(new Identity(7));

        $controller = new UserController('debug-user', new Module('debug'));

        $result = $controller->actionResetIdentity();

        self::assertFalse(
            $result->isGuest,
            'Reset must leave an authenticated identity in place.',
        );
    }

    public function testActionSetIdentitySwitchesActiveUserToPostedUserId(): void
    {
        $this->bootApp();

        Yii::$app->user->login(new Identity(1));
        Yii::$app->request->setBodyParams(['user_id' => 42]);

        $controller = new UserController('debug-user', new Module('debug'));

        $result = $controller->actionSetIdentity();

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
    }

    public function testBeforeActionForcesJsonResponseFormat(): void
    {
        $this->bootApp();

        Yii::$app->session->open();

        $controller = new UserController('debug-user', new Module('debug'));

        $action = $controller->createAction('reset-identity');

        self::assertNotNull(
            $action,
            "'reset-identity' must resolve to an action object.",
        );

        $controller->beforeAction($action); // @phpstan-ignore argument.type

        self::assertSame(
            Response::FORMAT_JSON,
            Yii::$app->response->format,
            "'beforeAction' must force the JSON response format.",
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

        $controller = new UserController('debug-user', new Module('debug'));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(
            'Identity not found.',
        );

        $controller->actionSetIdentity();
    }

    public function testThrowBadRequestHttpExceptionWhenIdentityClassIsNotConfigured(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'user' => [
                        'class' => User::class,
                        'identityClass' => \stdClass::class,
                        'enableSession' => true,
                        'enableAutoLogin' => false,
                    ],
                ],
            ],
        );

        Yii::$app->request->setBodyParams(['user_id' => 1]);

        $controller = new UserController('debug-user', new Module('debug'));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(
            'User component is not configured with an identity class.',
        );

        $controller->actionSetIdentity();
    }

    public function testThrowBadRequestHttpExceptionWhenSessionIsInactive(): void
    {
        $this->bootApp();

        // Drop the session id so `hasSessionId` reports `false`.
        unset($_COOKIE[Yii::$app->session->getName()]);

        $controller = new UserController('debug-user', new Module('debug'));

        $action = $controller->createAction('reset-identity');

        self::assertNotNull(
            $action,
            "'reset-identity' must resolve to an action object.",
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(
            'Need an active session',
        );

        $controller->beforeAction($action); // @phpstan-ignore argument.type
    }

    public function testThrowBadRequestHttpExceptionWhenUserIdIsNotScalar(): void
    {
        $this->bootApp();

        Yii::$app->request->setBodyParams(['user_id' => ['not-scalar']]);

        $controller = new UserController('debug-user', new Module('debug'));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(
            'Invalid user_id parameter.',
        );

        $controller->actionSetIdentity();
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
}
