<?php

declare(strict_types=1);

namespace yii\debug\actions;

use Override;
use Yii;
use yii\debug\models\UserSwitch;
use yii\web\{Action, BadRequestHttpException, IdentityInterface, Request, Response, User};

use function is_int;
use function is_string;
use function is_subclass_of;

/**
 * Switches the active identity to the user resolved from the posted `user_id`.
 *
 * JSON endpoint of the user-impersonation workflow exposed by the User Switch debug panel. Requires an active
 * session, enforced in {@see beforeRun()}.
 */
class SetIdentityAction extends Action
{
    /**
     * Runs the action.
     *
     * @param User $user User component whose identity is switched to the resolved user; bound by parameter name from
     * the application.
     * @param Request $request Request carrying the posted `user_id`.
     *
     * @throws BadRequestHttpException When the `user_id` parameter is missing or not a scalar, the identity class is
     * not configured, or the identity cannot be found.
     *
     * @return User User component reflecting the new impersonated identity.
     */
    public function run(User $user, Request $request): User
    {
        $user_id = $request->post('user_id');

        if (!is_string($user_id) && !is_int($user_id)) {
            throw new BadRequestHttpException(
                'Invalid user_id parameter.',
            );
        }

        $identityClass = $user->identityClass;

        if (!is_subclass_of($identityClass, IdentityInterface::class)) {
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

        $userSwitch = new UserSwitch(['userComponent' => $user]);

        $userSwitch->setUserByIdentity($newIdentity);

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

        return true;
    }
}
