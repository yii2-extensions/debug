<?php

declare(strict_types=1);

namespace yii\debug\models;

use Yii;
use yii\base\Model;
use yii\web\IdentityInterface;
use yii\web\User;

use function call_user_func;

/**
 * UserSwitch is a model used to temporary logging in another user.
 */
class UserSwitch extends Model
{
    /**
     * @var User user which we are currently switched to.
     */
    private User|string|null $_user = null;
    /**
     * @var User|null the main user who was originally logged in before switching.
     */
    private User|null $_mainUser = null;


    /**
     * @var string|User ID of the user component or a user object.
     */
    public string|User $userComponent = 'user';

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['user', 'mainUser'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'user' => 'Current User',
            'mainUser' => 'Main User',
        ];
    }

    /**
     * Get current user.
     */
    public function getUser(): User|string|null
    {
        return $this->_user;
    }

    /**
     * Get the main user.
     */
    public function getMainUser(): User|string|null
    {
        $currentUser = $this->getUser();

        if ($this->_mainUser === null && $currentUser->getIsGuest() === false) {
            $session = Yii::$app->getSession();

            if ($session->has('main_user')) {
                $mainUserId = $session->get('main_user');
                $mainIdentity = call_user_func([$currentUser->identityClass, 'findIdentity'], $mainUserId);
            } else {
                $mainIdentity = $currentUser->identity;
            }

            $mainUser = clone $currentUser;

            $mainUser->setIdentity($mainIdentity);

            $this->_mainUser = $mainUser;
        }

        return $this->_mainUser;
    }

    /**
     * Switch user.
     */
    public function setUser(User $user): void
    {
        // Check if user is currently active one
        $isCurrent = ($user->getId() === $this->getMainUser()?->getId());

        // Switch identity
        $this->getUser()?->switchIdentity($user->identity);

        if (!$isCurrent) {
            Yii::$app->getSession()->set('main_user', $this->getMainUser()?->getId());
        } else {
            Yii::$app->getSession()->remove('main_user');
        }
    }

    /**
     * Switch to user by identity.
     */
    public function setUserByIdentity(IdentityInterface $identity): void
    {
        $user = clone $this->getUser();

        $user->setIdentity($identity);
        $this->setUser($user);
    }

    /**
     * Reset to the main user.
     */
    public function reset(): void
    {
        $this->setUser($this->getMainUser());
    }

    /**
     * Checks if current user is main or not.
     */
    public function isMainUser(): bool
    {
        $user = $this->getUser();

        if ($user->getIsGuest()) {
            return true;
        }

        return $user->getId() === $this->getMainUser()?->getId();
    }
}
