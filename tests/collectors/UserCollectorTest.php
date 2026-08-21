<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use PHPForge\Debug\Helper\SensitiveDataRedactor;
use PHPUnit\Framework\Attributes\Group;
use ReflectionMethod;
use Yii;
use yii\debug\collectors\UserCollector;
use yii\debug\{LogTarget, Module};
use yii\debug\tests\support\stub\{Identity, ModelIdentity, SelectiveModelIdentity};
use yii\debug\tests\support\TestCase;
use yii\rbac\{BaseManager, Permission, Role};
use yii\web\{IdentityInterface, User};

use function array_column;

/**
 * Unit tests for {@see UserCollector} covering the identity capture, the RBAC roles/permissions narrowing, and the
 * startup/shutdown lifecycle.
 */
#[Group('collector')]
#[Group('user')]
final class UserCollectorTest extends TestCase
{
    public function testCaptureCapturesIdentityAttributesAndLabelsForModelIdentity(): void
    {
        $collector = $this->bootstrapCollectorWithIdentity(new ModelIdentity());

        $saved = $collector->capture()?->data();

        self::assertNotNull(
            $saved,
            'Identity save must succeed.',
        );
        self::assertSame(
            1,
            $saved['id'] ?? null,
            'Identity id must round-trip.',
        );
        $attributes = $saved['attributes'] ?? null;

        self::assertIsArray(
            $attributes,
            'Identity attributes must be an array.',
        );

        self::assertSame(
            [
                ['attribute' => 'id', 'label' => 'Id'],
                ['attribute' => 'username', 'label' => 'Username'],
            ],
            $attributes,
            'Model identity must surface attribute labels.',
        );
    }

    public function testCaptureCapturesIdentityForNonModelIdentity(): void
    {
        $collector = $this->bootstrapCollectorWithIdentity(new Identity(7));

        $saved = $collector->capture()?->data();

        self::assertNotNull(
            $saved,
            'Identity save must succeed.',
        );
        self::assertSame(
            7,
            $saved['id'] ?? null,
            'Identity id must round-trip.',
        );
        self::assertNull(
            $saved['attributes'] ?? null,
            'Non-Model identity must skip attribute labels.',
        );
    }

    public function testCaptureIgnoresAuthManagerMisconfiguration(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'user' => [
                        'class' => User::class,
                        'identityClass' => Identity::class,
                    ],
                ],
            ],
        );

        Yii::$app->user->login(new Identity(5));

        $module = new Module('debug', null, ['authManager' => 'authManager']);

        $module->logTarget = new LogTarget($module);

        $collector = $this->wireCollector($module);

        $saved = $collector->capture()?->data();

        self::assertNotNull(
            $saved,
            'Capture must complete despite missing auth manager.',
        );
        self::assertNull(
            $saved['roles'] ?? null,
            "Roles provider must stay 'null' on auth manager failure.",
        );
        self::assertNull(
            $saved['permissions'] ?? null,
            "Permissions provider must stay 'null' on auth manager failure.",
        );
    }

    public function testCapturePopulatesRbacRowsWhenAuthManagerWired(): void
    {
        $role = new Role();

        $role->name = 'admin';
        $role->description = 'Administrator';
        $role->createdAt = 1;
        $role->updatedAt = 2;

        $viewer = new Role();

        $viewer->name = 'viewer';
        $viewer->description = 'Viewer';
        $viewer->createdAt = 5;
        $viewer->updatedAt = 6;

        $permission = new Permission();

        $permission->name = 'manage';
        $permission->description = 'Manage';
        $permission->createdAt = 3;
        $permission->updatedAt = 4;

        $authManager = self::createStub(BaseManager::class);

        $authManager
            ->method('getRolesByUser')
            ->willReturn([$role->name => $role, $viewer->name => $viewer]);
        $authManager
            ->method('getPermissionsByUser')
            ->willReturn([$permission->name => $permission]);

        $this->mockWebApplication(
            [
                'components' => [
                    'user' => [
                        'class' => User::class,
                        'identityClass' => Identity::class,
                    ],
                ],
            ],
        );

        Yii::$app->user->login(new Identity(9));

        $module = new Module('debug', null, ['authManager' => $authManager]);

        $module->logTarget = new LogTarget($module);

        $collector = $this->wireCollector($module);

        $saved = $collector->capture()?->data();

        self::assertNotNull(
            $saved,
            'Capture must complete.',
        );

        $roles = $saved['roles'] ?? null;
        $permissions = $saved['permissions'] ?? null;

        self::assertIsArray(
            $roles,
            'Roles must be an array.',
        );
        self::assertIsArray(
            $permissions,
            'Permissions must be an array.',
        );
        self::assertSame(
            ['admin', 'viewer'],
            array_column($roles, 'name'),
            'Every role row must surface.',
        );
        self::assertCount(
            1,
            $permissions,
            'Permission rows must surface.',
        );
    }

    public function testCaptureRedactsSensitivePublicIdentityAttributes(): void
    {
        $identity = new class implements IdentityInterface {
            public string $access_token = 'identity-secret';
            public string $username = 'wilmer';

            public static function findIdentity($id): IdentityInterface|null
            {
                return null;
            }

            public static function findIdentityByAccessToken($token, $type = null): IdentityInterface|null
            {
                return null;
            }

            public function getAuthKey(): string
            {
                return 'auth-key';
            }

            public function getId(): int
            {
                return 1;
            }

            public function validateAuthKey($authKey): bool
            {
                return $authKey === 'auth-key';
            }
        };

        $saved = $this->bootstrapCollectorWithIdentity($identity)->capture()?->data();

        $identityData = $saved['identity'] ?? null;

        self::assertIsArray(
            $identityData,
            'Identity attributes must remain an array.',
        );
        self::assertSame(
            SensitiveDataRedactor::PLACEHOLDER,
            $identityData['access_token'] ?? null,
            'Sensitive identity attributes must be irreversibly redacted before capture.',
        );
        self::assertSame(
            "'wilmer'",
            $identityData['username'] ?? null,
            'Non-sensitive identity attributes must remain available.',
        );
    }

    public function testCaptureReturnsGuestSnapshotWhenNoIdentity(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'user' => [
                        'class' => User::class,
                        'identityClass' => Identity::class,
                    ],
                ],
            ],
        );

        $collector = new UserCollector();

        $collector->startup();

        self::assertSame(
            [
                'id' => null,
                'identity' => null,
                'attributes' => null,
                'roles' => null,
                'permissions' => null,
            ],
            $collector->capture()?->data(),
            'A configured guest user must yield the shared Guest snapshot.',
        );
    }

    public function testCaptureReturnsNullBeforeStartup(): void
    {
        $this->mockWebApplication();

        self::assertNull(
            (new UserCollector())->capture(),
            'Idle collector must record nothing.',
        );
    }

    public function testCaptureReturnsNullWhenNoUserComponent(): void
    {
        $this->mockWebApplication();

        $collector = new UserCollector();

        $collector->userComponent = 'nonexistent';

        $collector->startup();

        self::assertNull(
            $collector->capture(),
            'Missing user component must yield no snapshot.',
        );
    }
    public function testCollectorExtensionPointsRemainProtected(): void
    {
        foreach (['dataToString', 'getUser', 'identityData'] as $method) {
            self::assertTrue(
                (new ReflectionMethod(UserCollector::class, $method))->isProtected(),
                'Must remain protected to avoid accidental misuse.',
            );
        }
    }

    public function testDataToStringExportsNonStringValues(): void
    {
        $collector = new UserCollector();

        self::assertSame(
            'value',
            $this->invoke(
                $collector,
                'dataToString',
                ['value'],
            ),
            'String input must round-trip unchanged.',
        );

        $exported = $this->invoke(
            $collector,
            'dataToString',
            [['a' => 'b']],
        );

        self::assertIsString(
            $exported,
            'Export must produce a string.',
        );
        self::assertStringContainsString(
            "'a'",
            $exported,
            "Non-string input must be exported via 'VarDumper::export()'.",
        );
    }

    public function testIdentityDataUsesTheModelAttributeContract(): void
    {
        $collector = new UserCollector();

        self::assertSame(
            ['id' => 1],
            $this->invoke($collector, 'identityData', [new SelectiveModelIdentity()]),
        );
    }

    public function testIdPairsWithTheUserPanel(): void
    {
        self::assertSame(
            'user',
            (new UserCollector())->id(),
            "Stable ID must be 'user'.",
        );
    }

    public function testNormalizeStringKeyArrayKeepsEveryEntry(): void
    {
        self::assertSame(
            ['0' => 'first', 'name' => 'second'],
            $this->invokeStatic(UserCollector::class, 'normalizeStringKeyArray', [[0 => 'first', 'name' => 'second']]),
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
    }

    /**
     * Builds a started collector wired to a logged-in identity on a fully configured web application.
     *
     * @param IdentityInterface $identity Identity to log in.
     *
     * @return UserCollector Started collector.
     */
    private function bootstrapCollectorWithIdentity(IdentityInterface $identity): UserCollector
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'user' => [
                        'class' => User::class,
                        'identityClass' => $identity::class,
                    ],
                ],
            ],
        );

        Yii::$app->user->login($identity);

        $module = new Module('debug');

        $module->logTarget = new LogTarget($module);

        return $this->wireCollector($module);
    }

    /**
     * Creates a started collector bound to the given module.
     *
     * @param Module $module Debug module supplying the auth manager.
     *
     * @return UserCollector Started collector.
     */
    private function wireCollector(Module $module): UserCollector
    {
        $collector = new UserCollector();

        $collector->module = $module;

        $collector->startup();

        return $collector;
    }
}
