<?php

declare(strict_types=1);

namespace yii\debug\actions;

use Override;
use Yii;
use yii\web\{Action, BadRequestHttpException, MethodNotAllowedHttpException, Response};

/**
 * Guards the standalone identity mutation actions with the controller checks they do not otherwise receive.
 */
abstract class IdentityAction extends Action
{
    /**
     * Forces JSON output and requires an active session, POST, and a valid CSRF token.
     *
     * @throws BadRequestHttpException When the request has no active session or carries an invalid CSRF token.
     * @throws MethodNotAllowedHttpException When the request method is not POST.
     */
    #[Override]
    protected function beforeRun()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->session->hasSessionId) {
            throw new BadRequestHttpException(
                'Need an active session',
            );
        }

        $request = Yii::$app->request;

        if (!$request->getIsPost()) {
            throw new MethodNotAllowedHttpException(
                'Only POST requests are allowed.',
            );
        }

        if (!$request->validateCsrfToken()) {
            throw new BadRequestHttpException(
                Yii::t('yii', 'Unable to verify your data submission.'),
            );
        }

        return parent::beforeRun();
    }
}
