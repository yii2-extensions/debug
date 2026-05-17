<?php

declare(strict_types=1);

namespace yii\debug\tests\user;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\data\{ActiveDataProvider, ArrayDataProvider};
use yii\db\Connection;
use yii\debug\models\search\UserSearch;
use yii\debug\tests\support\stub\{ArIdentity, Identity, ModelIdentity};
use yii\debug\tests\support\TestCase;
use yii\web\User;

/**
 * Unit tests for {@see UserSearch} covering identity proxying (`__get`, `__set`, `attributes`, `rules`), the
 * `init()` resolution flow, and the data-provider dispatch (AR vs non-AR identity).
 */
#[Group('user')]
#[Group('search')]
final class UserSearchTest extends TestCase
{
    public function testAttributesAndRulesAreEmptyWhenNoUserComponent(): void
    {
        $this->mockApplication();

        $search = new UserSearch();

        self::assertNull(
            $search->identityImplement,
            "Console app exposes no user component, so the identity proxy must stay 'null'.",
        );
        self::assertSame(
            [],
            $search->attributes(),
            'Missing identity must expose no attributes.',
        );
        self::assertSame(
            [],
            $search->rules(),
            'Missing identity must declare no validation rules.',
        );
    }

    public function testAttributesAndRulesProxyToModelIdentity(): void
    {
        $this->bootWebAppWithIdentity(ModelIdentity::class);

        $search = new UserSearch();

        $attributes = $search->attributes();

        self::assertContains(
            'id',
            $attributes,
            "Identity 'id' attribute must surface.",
        );
        self::assertContains(
            'username',
            $attributes,
            "Identity 'username' attribute must surface.",
        );

        $rules = $search->rules();

        self::assertNotEmpty(
            $rules,
            'Rules must be derived from the identity attribute list.',
        );

        $firstRule = $rules[0] ?? null;

        self::assertIsArray(
            $firstRule,
            'First derived rule must be a tuple.',
        );
        self::assertSame(
            'safe',
            $firstRule[1] ?? null,
            "Derived rule must mark identity fields as 'safe'.",
        );
    }

    public function testGetForwardsToIdentityImplementForVirtualProperties(): void
    {
        $this->bootWebAppWithIdentity(ArIdentity::class, withDb: true);

        $search = new UserSearch();

        $identity = $search->identityImplement;

        self::assertInstanceOf(
            ArIdentity::class,
            $identity,
            'AR identity proxy must be wired.',
        );

        $identity->setAttribute('username', 'forwarded');

        self::assertSame(
            'forwarded',
            $search->__get('username'),
            "'__get' must forward to the identity proxy and return its attribute value.",
        );
    }

    public function testGetReturnsNullWhenNoIdentityIsResolved(): void
    {
        $this->mockApplication();

        self::assertNull(
            (new UserSearch())->__get('username'),
            "Without an identity proxy '__get' must surface 'null'.",
        );
    }

    public function testInitSkipsNonModelIdentityImplementations(): void
    {
        $this->bootWebAppWithIdentity(Identity::class);

        $search = new UserSearch();

        self::assertNull(
            $search->identityImplement,
            "Identity classes that don't extend Model must leave the proxy unset.",
        );
    }

    public function testSearchAppliesAttributeFiltersOnActiveRecordIdentity(): void
    {
        $this->bootWebAppWithIdentity(ArIdentity::class, withDb: true);

        $provider = (new UserSearch())->search(['UserSearch' => ['username' => 'admin']]);

        self::assertInstanceOf(
            ActiveDataProvider::class,
            $provider,
            "AR-backed 'search()' must build an 'ActiveDataProvider'.",
        );
        self::assertGreaterThan(
            0,
            $provider->getTotalCount(),
            "'admin' fixture row must survive the LIKE filter.",
        );
    }

    public function testSearchReturnsActiveDataProviderForActiveRecordIdentity(): void
    {
        $this->bootWebAppWithIdentity(ArIdentity::class, withDb: true);

        $provider = (new UserSearch())->search([]);

        self::assertInstanceOf(
            ActiveDataProvider::class,
            $provider,
            'AR identity must produce an ActiveDataProvider.',
        );
    }

    public function testSearchReturnsEmptyArrayProviderForNonActiveRecordIdentity(): void
    {
        $this->bootWebAppWithIdentity(ModelIdentity::class);

        $provider = (new UserSearch())->search([]);

        self::assertInstanceOf(
            ArrayDataProvider::class,
            $provider,
            'Non-AR identity must fall back to an empty ArrayDataProvider.',
        );
        self::assertSame(
            0,
            $provider->getTotalCount(),
            'Empty fallback provider must report zero rows.',
        );
    }

    public function testSetForwardsToIdentityImplementForVirtualProperties(): void
    {
        $this->bootWebAppWithIdentity(ArIdentity::class, withDb: true);

        $search = new UserSearch();

        $identity = $search->identityImplement;

        self::assertInstanceOf(
            ArIdentity::class,
            $identity,
            'AR identity proxy must be wired.',
        );

        $search->__set('username', 'written');

        self::assertSame(
            'written',
            $identity->getAttribute('username'),
            "'__set' must forward through the identity proxy onto the AR attribute store.",
        );
    }

    public function testSetIsNoOpWhenNoIdentityIsResolved(): void
    {
        $this->mockApplication();

        $search = new UserSearch();

        $search->__set('username', 'ignored');

        self::assertNull(
            $search->identityImplement,
            "Without an identity proxy '__set' must short-circuit without errors.",
        );
    }

    /**
     * @param class-string $identityClass
     */
    private function bootWebAppWithIdentity(string $identityClass, bool $withDb = false): void
    {
        $config = [
            'components' => [
                'user' => [
                    'class' => User::class,
                    'identityClass' => $identityClass,
                    'enableSession' => false,
                ],
            ],
        ];

        if ($withDb) {
            $config['components']['db'] = [
                'class' => Connection::class,
                'dsn' => 'sqlite::memory:',
            ];
        }

        $this->mockWebApplication($config);

        if ($withDb) {
            Yii::$app->db->createCommand()
                ->createTable(
                    'stub_users',
                    ['id' => 'INTEGER PRIMARY KEY', 'username' => 'TEXT NOT NULL'],
                )
                ->execute();
            Yii::$app->db->createCommand()
                ->insert('stub_users', ['id' => 1, 'username' => 'admin'])
                ->execute();
        }
    }
}
