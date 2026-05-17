<?php

declare(strict_types=1);

namespace yii\debug\controllers;

use Yii;
use yii\base\InvalidConfigException;
use yii\debug\models\UserSwitch;
use yii\web\{BadRequestHttpException, Controller, Response, User};

use function is_int;
use function is_string;

/**
 * Drives the user-impersonation workflow exposed by the User Switch debug panel.
 *
 * Provides JSON endpoints to swap the active identity to an impersonated user (`set-identity`) and to restore the
 * original identity captured before the swap (`reset-identity`). Every action requires an active session, enforced in
 * {@see beforeAction()}.
 */
class UserController extends Controller
{
    /**
     * Restores the original identity captured before the impersonation swap.
     *
     * @throws InvalidConfigException When the user component is not properly configured.
     *
     * @return User User component reflecting the restored identity.
     */
    public function actionResetIdentity(): User
    {
        $userSwitch = new UserSwitch();

        $userSwitch->reset();

        return Yii::$app->user;
    }

    /**
     * Switches the active identity to the user resolved from the posted `user_id`.
     *
     * @throws BadRequestHttpException When the `user_id` parameter is missing or not a scalar, the identity class is
     * not configured, or the identity cannot be found.
     *
     * @return User User component reflecting the new impersonated identity.
     */
    public function actionSetIdentity(): User
    {
        $user_id = Yii::$app->request->post('user_id');

        if (!is_string($user_id) && !is_int($user_id)) {
            throw new BadRequestHttpException(
                'Invalid user_id parameter.',
            );
        }

        $identityClass = Yii::$app->user->identityClass;

        if (!is_subclass_of($identityClass, \yii\web\IdentityInterface::class)) {
            throw new BadRequestHttpException(
                'User component is not configured with an identity class.',
            );
        }

        $newIdentity = $identityClass::findIdentity($user_id);

        if ($newIdentity === null) {
            throw new BadRequestHttpException(
                'Identity not found.',
            );
        }

        $userSwitch = new UserSwitch();

        $userSwitch->setUserByIdentity($newIdentity);

        return Yii::$app->user;
    }

    /**
     * Forces the response format to JSON and requires an active session before delegating to the parent guard.
     *
     * @throws BadRequestHttpException When the current request has no active session.
     */
    public function beforeAction($action): bool
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->session->hasSessionId) {
            throw new BadRequestHttpException(
                'Need an active session',
            );
        }

        return parent::beforeAction($action);
    }
}
