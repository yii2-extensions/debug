<?php

declare(strict_types=1);

namespace yii\debug\models;

use RuntimeException;
use Yii;
use yii\base\{InvalidConfigException, Model};
use yii\web\{IdentityInterface, User};

use function is_int;
use function is_string;

/**
 * Backs the user-impersonation workflow by swapping the active session identity to another user.
 *
 * Preserves the original identity in the session (`main_user`) so {@see reset()} can restore it once the impersonator
 * is done. Every accessor resolves the user component lazily through {@see getUser()} so unit tests can inject a
 * pre-built {@see User} instance into {@see $userComponent}.
 */
class UserSwitch extends Model
{
    /**
     * Component ID of the user component, or a {@see User} instance to operate on directly.
     */
    public string|User $userComponent = 'user';

    /**
     * Cached main user: the identity originally logged in before any switch.
     */
    private User|null $mainUser = null;
    /**
     * Cached current user: the identity the session is currently switched to.
     */
    private User|null $user = null;

    /**
     * @return array<string, string> Form labels keyed by attribute name.
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
     * Reads the captured id from the session when present; otherwise treats the current identity as the main one.
     *
     * @throws InvalidConfigException When the user component cannot be resolved.
     */
    public function getMainUser(): User
    {
        $currentUser = $this->getUser();

        if ($this->mainUser !== null) {
            return $this->mainUser;
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

        return $this->mainUser = $mainUser;
    }

    /**
     * Returns the user component bound to this switch model, resolving it lazily on first call.
     *
     * @throws InvalidConfigException When the configured component ID does not resolve to a {@see User} instance.
     */
    public function getUser(): User
    {
        if ($this->user !== null) {
            return $this->user;
        }

        if ($this->userComponent instanceof User) {
            return $this->user = $this->userComponent;
        }

        $resolved = Yii::$app->get($this->userComponent, false);

        if (!$resolved instanceof User) {
            throw new InvalidConfigException(
                "Application component '{$this->userComponent}' must be a 'yii\\web\\User' instance.",
            );
        }

        return $this->user = $resolved;
    }

    /**
     * Returns whether the current identity is the main user (or a guest).
     *
     * @throws InvalidConfigException When the user component cannot be resolved.
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
     * Restores the session to the main user captured before the first switch.
     *
     * @throws InvalidConfigException When the user component cannot be resolved.
     */
    public function reset(): void
    {
        $this->setUser($this->getMainUser());
    }

    /**
     * @return array<int, array<int|string, mixed>> Validation rules consumed by {@see Model::validate()}.
     */
    public function rules(): array
    {
        return [
            [['user', 'mainUser'], 'safe'],
        ];
    }

    /**
     * Switches the session to the given user and tracks the main user id when impersonating.
     *
     * @throws InvalidConfigException When the user component cannot be resolved.
     * @throws RuntimeException When the supplied user has no identity attached.
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
     * @throws InvalidConfigException When the user component cannot be resolved.
     */
    public function setUserByIdentity(IdentityInterface $identity): void
    {
        $user = clone $this->getUser();

        $user->setIdentity($identity);

        $this->setUser($user);
    }
}
