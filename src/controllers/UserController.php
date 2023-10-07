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

/**
 * User controller
 */
class UserController extends Controller
{
    /**
     * {@inheritdoc}
     *
     * @throws BadRequestHttpException if session is not active.
     */
    public function beforeAction($action): bool
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->session->hasSessionId) {
            throw new BadRequestHttpException('Need an active session');
        }

        return parent::beforeAction($action);
    }

    /**
     * Set new identity, switch user.
     *
     * @throws InvalidConfigException if user component is not found.
     */
    public function actionSetIdentity(): User
    {
        $user_id = Yii::$app->request->post('user_id');

        $userSwitch = new UserSwitch();
        $newIdentity = Yii::$app->user->identity->findIdentity($user_id);
        $userSwitch->setUserByIdentity($newIdentity);

        return Yii::$app->user;
    }

    /**
     * Reset identity, switch to the main user.
     */
    public function actionResetIdentity(): User
    {
        $userSwitch = new UserSwitch();
        $userSwitch->reset();

        return Yii::$app->user;
    }
}
