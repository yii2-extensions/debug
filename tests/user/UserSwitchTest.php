<?php

declare(strict_types=1);

namespace yii\debug\tests\user;

use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\models\UserSwitch;
use yii\debug\tests\support\stub\Identity;
use yii\debug\tests\support\TestCase;
use yii\web\User;

/**
 * Unit tests for {@see UserSwitch} covering identity resolution (lazy user component, string id vs `User` instance),
 * the main-user session snapshot used by the impersonation workflow, `isMainUser()` semantics, and the `setUser()` /
 * `setUserByIdentity()` / `reset()` switch dispatch.
 */
#[Group('user')]
final class UserSwitchTest extends TestCase
{
    public function testAttributeLabelsExposeFormFields(): void
    {
        $labels = (new UserSwitch())->attributeLabels();

        self::assertArrayHasKey(
            'user',
            $labels,
            "'user' label must be defined.",
        );
        self::assertArrayHasKey(
            'mainUser',
            $labels,
            "'mainUser' label must be defined.",
        );
    }

    public function testGetMainUserCachesResolvedInstance(): void
    {
        $this->bootApp();

        Yii::$app->user->login(new Identity(3));

        $switch = new UserSwitch();

        self::assertSame(
            $switch->getMainUser(),
            $switch->getMainUser(),
            'Repeated calls must return the cached main-user instance.',
        );
    }

    public function testGetMainUserIgnoresNonScalarSnapshotInSession(): void
    {
        $this->bootApp();

        Yii::$app->user->login(new Identity(2));
        Yii::$app->session->set('main_user', ['not-scalar']);

        $switch = new UserSwitch();

        self::assertNull(
            $switch->getMainUser()->identity,
            "Non-scalar 'main_user' snapshots must yield a 'User' with no identity attached.",
        );
    }

    public function testGetMainUserResolvesSnapshotFromSession(): void
    {
        $this->bootApp();

        Yii::$app->user->login(new Identity(11));
        Yii::$app->session->set('main_user', 99);

        $switch = new UserSwitch();

        self::assertSame(
            99,
            $switch->getMainUser()->getId(),
            "'main_user' session snapshot must be resolved through 'findIdentity()' as the main user.",
        );
    }

    public function testGetMainUserReturnsCurrentWhenGuest(): void
    {
        $this->bootApp();

        $switch = new UserSwitch();

        self::assertTrue(
            $switch->getMainUser()->getIsGuest(),
            'Guest sessions must surface the guest user as the main user.',
        );
    }

    public function testGetMainUserReturnsLoggedInIdentityWhenSessionLacksSnapshot(): void
    {
        $this->bootApp();

        Yii::$app->user->login(new Identity(7));

        $switch = new UserSwitch();

        self::assertSame(
            7,
            $switch->getMainUser()->getId(),
            "When 'main_user' is absent the active identity must surface as the main user.",
        );
    }

    public function testGetUserAcceptsUserInstanceVerbatim(): void
    {
        $this->bootApp();

        $user = Yii::$app->user;

        $switch = new UserSwitch(['userComponent' => $user]);

        self::assertSame(
            $user,
            $switch->getUser(),
            "When 'userComponent' is already a 'User' instance it must be returned verbatim.",
        );
    }

    public function testGetUserCachesResolvedComponent(): void
    {
        $this->bootApp();

        $switch = new UserSwitch();

        self::assertSame(
            $switch->getUser(),
            $switch->getUser(),
            'Repeated calls must return the cached user component.',
        );
    }

    public function testIsMainUserReturnsFalseAfterSwitchingIdentity(): void
    {
        $this->bootApp();

        Yii::$app->user->login(new Identity(5));

        $switch = new UserSwitch();

        $switch->setUserByIdentity(new Identity(13));

        self::assertFalse(
            $switch->isMainUser(),
            "After switching to a different identity 'isMainUser' must report 'false'.",
        );
    }

    public function testIsMainUserReturnsTrueForGuest(): void
    {
        $this->bootApp();

        $switch = new UserSwitch();

        self::assertTrue(
            $switch->isMainUser(),
            "Guest sessions must report 'isMainUser()' as 'true'.",
        );
    }

    public function testIsMainUserReturnsTrueWhenCurrentMatchesMain(): void
    {
        $this->bootApp();

        Yii::$app->user->login(new Identity(5));

        $switch = new UserSwitch();

        self::assertTrue(
            $switch->isMainUser(),
            "Active identity matching the main user must report 'isMainUser' as 'true'.",
        );
    }

    public function testResetRestoresMainIdentity(): void
    {
        $this->bootApp();

        Yii::$app->user->login(new Identity(5));

        $switch = new UserSwitch();

        $switch->setUserByIdentity(new Identity(13));

        self::assertFalse(
            $switch->isMainUser(),
            'Sanity: setup must leave a non-main identity active.',
        );

        $switch->reset();

        self::assertTrue(
            $switch->isMainUser(),
            "After 'reset()' the session must hold the captured main identity again.",
        );
    }

    public function testRulesMarkBothAttributesSafe(): void
    {
        $firstRule = (new UserSwitch())->rules()[0] ?? null;

        self::assertIsArray(
            $firstRule,
            'First rule must be a configuration tuple.',
        );
        self::assertSame(
            'safe',
            $firstRule[1] ?? null,
            "First rule must mark 'user'/'mainUser' as 'safe'."
        );
    }

    public function testSetUserByIdentityCapturesMainUserSnapshot(): void
    {
        $this->bootApp();

        Yii::$app->user->login(new Identity(5));

        $switch = new UserSwitch();

        $switch->setUserByIdentity(new Identity(13));

        self::assertSame(
            5,
            Yii::$app->session->get('main_user'),
            "Switching identity must persist the original id under the 'main_user' session key.",
        );
        self::assertSame(
            13,
            Yii::$app->user->getId(),
            'Active session identity must be the impersonated user.',
        );
    }

    public function testSetUserClearsSessionSnapshotWhenSwitchingBackToMain(): void
    {
        $this->bootApp();

        Yii::$app->user->login(new Identity(5));

        $switch = new UserSwitch();

        $switch->setUserByIdentity(new Identity(13));

        self::assertTrue(
            Yii::$app->session->has('main_user'),
            'Switching to another identity must capture the main user snapshot.',
        );

        $switch->setUser($switch->getMainUser());

        self::assertFalse(
            Yii::$app->session->has('main_user'),
            "Switching back to the main user must remove the 'main_user' snapshot.",
        );
    }

    public function testThrowInvalidConfigExceptionWhenUserComponentIdResolvesToNonUser(): void
    {
        $this->bootApp();

        Yii::$app->set('weirdcomponent', new \stdClass());

        $switch = new UserSwitch(['userComponent' => 'weirdcomponent']);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage("must be a 'yii\\web\\User' instance");

        $switch->getUser();
    }

    public function testThrowRuntimeExceptionWhenSetUserCalledWithoutIdentity(): void
    {
        $this->bootApp();

        $switch = new UserSwitch();

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'without an attached identity',
        );

        $switch->setUser(Yii::$app->user);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        $_COOKIE = [];
    }

    private function bootApp(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'user' => [
                        'class' => User::class,
                        'identityClass' => Identity::class,
                        'enableSession' => true,
                        'enableAutoLogin' => false,
                    ],
                ],
            ],
        );
    }
}
