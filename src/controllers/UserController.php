<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

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
 *
 * @author Semen Dubina <yii2debug@sam002.net>
 * @since 2.0.10
 */
class UserController extends Controller
{
    /**
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
     * @throws InvalidConfigException if a user component is not found.
     * @throws BadRequestHttpException if user is not found.
     */
    public function actionSetIdentity(): User
    {
        $user_id = Yii::$app->request->post('user_id');
        $newIdentity = Yii::$app->user->identity->findIdentity($user_id);

        if ($newIdentity === null) {
            throw new BadRequestHttpException('User not found');
        }

        $userSwitch = new UserSwitch();
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
