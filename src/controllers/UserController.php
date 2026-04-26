<?php

declare(strict_types=1);

namespace yii\debug\controllers;

use Yii;
use yii\base\InvalidConfigException;
use yii\debug\models\UserSwitch;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\Response;
use yii\web\User;

use function is_int;
use function is_string;

/**
 * User controller for switching user identity in debug mode.
 */
class UserController extends Controller
{
    /**
     * Reset identity, switch to main user.
     *
     * @throws InvalidConfigException if the user component is not properly configured.
     *
     * @return User Current user after resetting identity.
     */
    public function actionResetIdentity(): User
    {
        $userSwitch = new UserSwitch();

        $userSwitch->reset();

        return Yii::$app->user;
    }

    /**
     * Set new identity, switch user.
     *
     * @throws BadRequestHttpException if the user_id parameter is missing or invalid, or if the identity class is not
     * properly configured, or if the identity cannot be found.
     *
     * @return User Current user after setting new identity.
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
     * @throws BadRequestHttpException if there is no active session.
     */
    public function beforeAction($action): bool
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->session->hasSessionId) {
            throw new BadRequestHttpException('Need an active session');
        }

        return parent::beforeAction($action);
    }
}
