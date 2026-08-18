<?php

declare(strict_types=1);

namespace yii\debug\actions;

use Override;
use Yii;
use yii\debug\models\UserSwitch;
use yii\web\{Action as WebAction, BadRequestHttpException, Response, User};

/**
 * Restores the original identity captured before the impersonation swap.
 *
 * JSON endpoint of the user-impersonation workflow exposed by the User Switch debug panel. Requires an active
 * session, enforced in {@see beforeRun()}.
 */
class ResetIdentityAction extends WebAction
{
    /**
     * Runs the action.
     *
     * @param User $user User component whose original identity is restored; bound by parameter name from the
     * application.
     *
     * @return User User component reflecting the restored identity.
     */
    public function run(User $user): User
    {
        $userSwitch = new UserSwitch(['userComponent' => $user]);

        $userSwitch->reset();

        return $user;
    }

    /**
     * Forces the JSON response format and requires an active session before the action body runs.
     *
     * @throws BadRequestHttpException When the current request has no active session.
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

        return parent::beforeRun();
    }
}
