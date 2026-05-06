<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\models;

use RuntimeException;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\web\IdentityInterface;
use yii\web\User;

use function is_int;
use function is_string;

/**
 * UserSwitch model used to temporarily log in as another user.
 */
class UserSwitch extends Model
{
    /**
     * Component ID of the user component, or a {@see User} instance to operate on directly.
     */
    public string|User $userComponent = 'user';

    /**
     * Cached main user — the user originally logged in before any identity switch.
     */
    private User|null $_mainUser = null;
    /**
     * Cached current user — the user the session is currently switched to.
     */
    private User|null $_user = null;

    /**
     * @return array<string, string>
     */
    public function attributeLabels(): array
    {
        return [
            'user' => 'Current User',
            'mainUser' => 'Main User',
        ];
    }

    /**
     * Returns the main user; the original identity captured on the first switch.
     *
     * @throws InvalidConfigException if the user component cannot be resolved.
     */
    public function getMainUser(): User
    {
        $currentUser = $this->getUser();

        if ($this->_mainUser !== null) {
            return $this->_mainUser;
        }

        if ($currentUser->getIsGuest()) {
            return $currentUser;
        }

        $session = Yii::$app->getSession();

        $mainIdentity = null;

        if ($session->has('main_user')) {
            $mainUserId = $session->get('main_user');

            if (is_int($mainUserId) || is_string($mainUserId)) {
                $mainIdentity = $currentUser->identityClass::findIdentity($mainUserId);
            }
        } else {
            $mainIdentity = $currentUser->identity;
        }

        $mainUser = clone $currentUser;

        $mainUser->setIdentity($mainIdentity);

        return $this->_mainUser = $mainUser;
    }

    /**
     * Returns the current user component bound to this switch model.
     *
     * @throws InvalidConfigException If the user component cannot be resolved.
     */
    public function getUser(): User
    {
        if ($this->_user !== null) {
            return $this->_user;
        }

        if ($this->userComponent instanceof User) {
            return $this->_user = $this->userComponent;
        }

        $resolved = Yii::$app->get($this->userComponent, false);

        if (!$resolved instanceof User) {
            throw new InvalidConfigException(
                "Application component '{$this->userComponent}' must be a 'yii\\web\\User' instance.",
            );
        }

        return $this->_user = $resolved;
    }

    /**
     * Checks whether the current user matches the main user.
     *
     * @throws InvalidConfigException
     */
    public function isMainUser(): bool
    {
        $user = $this->getUser();

        if ($user->getIsGuest()) {
            return true;
        }

        return $user->getId() === $this->getMainUser()->getId();
    }

    /**
     * Resets the session back to the main user.
     *
     * @throws InvalidConfigException if the user component cannot be resolved.
     */
    public function reset(): void
    {
        $this->setUser($this->getMainUser());
    }

    /**
     * @return array<int, array<int|string, mixed>>
     */
    public function rules(): array
    {
        return [
            [['user', 'mainUser'], 'safe'],
        ];
    }

    /**
     * Switches the session to the given user.
     *
     * @throws InvalidConfigException if the user component cannot be resolved.
     * @throws RuntimeException if the supplied user has no identity attached.
     */
    public function setUser(User $user): void
    {
        $identity = $user->identity;

        if ($identity === null) {
            throw new RuntimeException('Cannot switch to a user without an attached identity.');
        }

        $isCurrent = ($user->getId() === $this->getMainUser()->getId());

        $this->getUser()->switchIdentity($identity);

        if ($isCurrent) {
            Yii::$app->getSession()->remove('main_user');

            return;
        }

        Yii::$app->getSession()->set('main_user', $this->getMainUser()->getId());
    }

    /**
     * Switches the session to the user identified by `$identity`.
     *
     * @throws InvalidConfigException
     */
    public function setUserByIdentity(IdentityInterface $identity): void
    {
        $user = clone $this->getUser();

        $user->setIdentity($identity);

        $this->setUser($user);
    }
}
