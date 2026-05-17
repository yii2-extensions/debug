<?php

declare(strict_types=1);

namespace yii\debug\tests\user;

use PHPUnit\Framework\Attributes\Group;
use stdClass;
use Yii;
use yii\base\{InvalidConfigException, Model};
use yii\data\ArrayDataProvider;
use yii\debug\LogTarget;
use yii\debug\models\search\{UserSearch, UserSearchInterface};
use yii\debug\models\UserSwitch;
use yii\debug\Module;
use yii\debug\panels\UserPanel;
use yii\debug\tests\support\stub\{
    ArIdentity,
    Identity,
    ModelIdentity,
    NoSearchFilterModel,
    SearchableFilterModel,
    UserControllerNoAction,
};
use yii\debug\tests\support\TestCase;
use yii\rbac\{BaseManager, Permission, Role};
use yii\web\{Controller, IdentityInterface, User};

/**
 * Unit tests for {@see UserPanel} covering identity capture, the RBAC roles/permissions narrowing, the user-switch
 * affordances, the toolbar variant selection, and the rendered detail/summary views.
 */
#[Group('panel')]
#[Group('user')]
final class UserPanelTest extends TestCase
{
    public function testCanSearchUsersReturnsFalseWhenFilterModelIsNotConfigured(): void
    {
        $panel = $this->makePanel(UserPanel::class);

        self::assertFalse(
            $panel->canSearchUsers(),
            "'null' filter model must be rejected.",
        );
    }

    public function testCanSearchUsersReturnsFalseWhenFilterModelLacksSearch(): void
    {
        $panel = $this->makePanel(UserPanel::class);

        $panel->filterModel = new NoSearchFilterModel();

        self::assertFalse(
            $panel->canSearchUsers(),
            "Filter model without 'search' method must be rejected.",
        );
    }

    public function testCanSearchUsersReturnsTrueForUserSearchInterface(): void
    {
        $panel = $this->makePanel(UserPanel::class);

        $panel->filterModel = new SearchableFilterModel();

        self::assertTrue(
            $panel->canSearchUsers(),
            'UserSearchInterface model must be searchable.',
        );
    }

    public function testCanSwitchUserReturnsFalseWhenAccessRuleDenies(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new Identity(1));

        self::assertFalse(
            $panel->canSwitchUser(),
            "Default rule ('allow=false') must deny switching.",
        );
    }

    public function testCanSwitchUserReturnsFalseWhenControllerIsNotUserController(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new Identity(1));

        $module = $panel->module ?? self::fail('Module must be wired.');

        $module->controllerMap['user'] = [
            'class' => Controller::class,
        ];

        self::assertFalse(
            $panel->canSwitchUser(),
            'Non-UserController override must deny switching.',
        );
    }

    public function testCanSwitchUserReturnsFalseWhenSetIdentityActionMissing(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new Identity(1));

        $module = $panel->module ?? self::fail('Module must be wired.');

        $module->controllerMap['user'] = UserControllerNoAction::class;

        self::assertFalse(
            $panel->canSwitchUser(),
            "Missing 'set-identity' action must deny switching.",
        );
    }

    public function testCanSwitchUserReturnsFalseWhenUserIsGuest(): void
    {
        $panel = $this->bootstrapPanelWithGuest();

        self::assertFalse(
            $panel->canSwitchUser(),
            'Guest must not be allowed to switch user.',
        );
    }

    public function testCanSwitchUserReturnsTrueWhenAccessRuleAllows(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new Identity(1));

        $panel->ruleUserSwitch = [
            'allow' => true,
        ];

        self::assertTrue(
            $panel->canSwitchUser(),
            "Allow='true' rule must grant switching.",
        );
    }

    public function testDataToStringExportsNonStringValues(): void
    {
        $panel = $this->makePanel(UserPanel::class);

        self::assertSame(
            'value',
            $this->invoke(
                $panel,
                'dataToString',
                ['value'],
            ),
            'String input must round-trip unchanged.',
        );

        $exported = $this->invoke(
            $panel,
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

    public function testGetDetailRendersGuestPlaceholderWhenIdentityMissing(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new ModelIdentity());

        $this->setInaccessibleProperty($panel, 'data', ['id' => null, 'identity' => null]);

        self::assertStringContainsString(
            'Is guest',
            $panel->getDetail(),
            "Missing identity must render the 'Is guest' fallback.",
        );
    }

    public function testGetDetailRendersIdentityView(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new ModelIdentity());

        $panel->data = [
            'id' => 1,
            'identity' => ['id' => "'1'", 'username' => "'wilmer'"],
            'attributes' => [
                ['attribute' => 'id', 'label' => 'Id'],
                ['attribute' => 'username', 'label' => 'Username'],
            ],
            'rolesProvider' => null,
            'permissionsProvider' => null,
        ];

        self::assertNotEmpty(
            $panel->getDetail(),
            'Detail view must produce markup.',
        );
    }

    public function testGetDetailRendersResetButtonWhenSwitchIsActive(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new ModelIdentity(), filterModel: new SearchableFilterModel());

        self::assertNotNull(
            $panel->module,
            'Module must be wired.',
        );
        self::assertNotNull(
            $panel->userSwitch,
            'UserSwitch must be wired.',
        );

        Yii::$app->controller = new Controller('debug', $panel->module);

        $panel->ruleUserSwitch = ['allow' => true];

        // Cache a different mainUser on the bound UserSwitch so 'isMainUser()' returns 'false' and the reset section
        // renders. The cached id differs from the active identity's id ('1').
        $mainIdentity = new ModelIdentity();

        $mainIdentity->id = 99;

        $mainUser = new User(['identityClass' => ModelIdentity::class]);

        $mainUser->setIdentity($mainIdentity);

        $this->setInaccessibleProperty(
            $panel->userSwitch,
            'mainUser',
            $mainUser,
        );

        $panel->data = [
            'id' => 1,
            'identity' => [
                'id' => "'1'",
                'username' => "'wilmer'",
            ],
            'attributes' => [
                [
                    'attribute' => 'id',
                    'label' => 'Id',
                ],
            ],
            'rolesProvider' => null,
            'permissionsProvider' => null,
        ];

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'Reset to',
            $html,
            'Reset button must surface when switched.',
        );
    }

    public function testGetDetailRendersRolesAndSwitchSectionsWhenPanelAllowsThem(): void
    {
        $role = new Role();

        $role->name = 'admin';
        $role->description = 'Administrator';

        $permission = new Permission();

        $permission->name = 'manage';
        $permission->description = 'Manage';

        $panel = $this->bootstrapPanelWithIdentity(new ModelIdentity(), filterModel: new SearchableFilterModel());

        self::assertNotNull($panel->module, 'Module must be wired.');

        Yii::$app->controller = new Controller('debug', $panel->module);

        // Allow user switching so detail.php pulls in 'switch.php'.
        $panel->ruleUserSwitch = ['allow' => true];

        $panel->data = [
            'id' => 1,
            'identity' => [
                'id' => "'1'",
                'username' => "'wilmer'",
            ],
            'attributes' => [
                [
                    'attribute' => 'id',
                    'label' => 'Id',
                ],
                [
                    'attribute' => 'username',
                    'label' => 'Username',
                ],
            ],
            'rolesProvider' => new ArrayDataProvider(['allModels' => [$role]]),
            'permissionsProvider' => new ArrayDataProvider(['allModels' => [$permission]]),
        ];

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'Roles',
            $html,
            'Roles section must render.',
        );
        self::assertStringContainsString(
            'Permissions',
            $html,
            'Permissions section must render.',
        );
        self::assertStringContainsString(
            'Switch user',
            $html,
            'Switch user section must render.',
        );
    }

    public function testGetNameAndIcon(): void
    {
        $panel = $this->makePanel(UserPanel::class);

        self::assertSame(
            'User',
            $panel->getName(),
            "Display name must be 'User'.",
        );
        self::assertSame(
            'user',
            $panel->getToolbarIcon(),
            "Icon key must be 'user'.",
        );
    }

    public function testGetSummaryRendersChip(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new Identity(1));

        self::assertNotEmpty(
            $panel->getSummary(),
            'Summary view must produce markup.',
        );
    }

    public function testGetSummaryRendersMainUserChipWhenIdentityIsKnown(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new Identity(1));

        $this->setInaccessibleProperty(
            $panel,
            'data',
            ['id' => 1],
        );

        $html = $panel->getSummary();

        self::assertStringContainsString(
            'toolbar-label-info',
            $html,
            'Main-user chip must use the info label.',
        );
    }

    public function testGetSummaryRendersSwitchingChipAndSwitchIconWhenSwitchActive(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new ModelIdentity(), filterModel: new SearchableFilterModel());

        self::assertNotNull(
            $panel->module,
            'Module must be wired.',
        );
        self::assertNotNull(
            $panel->userSwitch,
            'UserSwitch must be wired.',
        );

        Yii::$app->controller = new Controller('debug', $panel->module);

        $panel->ruleUserSwitch = ['allow' => true];

        // Make 'isMainUser()' return 'false' by caching a different mainUser on the bound UserSwitch.
        $mainIdentity = new ModelIdentity();

        $mainIdentity->id = 99;

        $mainUser = new User(['identityClass' => ModelIdentity::class]);

        $mainUser->setIdentity($mainIdentity);

        $this->setInaccessibleProperty(
            $panel->userSwitch,
            'mainUser',
            $mainUser
        );
        $this->setInaccessibleProperty(
            $panel,
            'data',
            ['id' => 1]
        );

        $html = $panel->getSummary();

        self::assertStringContainsString(
            'switching',
            $html,
            'Summary must mark the switching state.'
        );
        self::assertStringContainsString(
            'toolbar-switch-icon',
            $html,
            'Switch icon must surface when allowed.'
        );
    }

    public function testGetToolbarItemsRendersGuestWhenNoIdInData(): void
    {
        $panel = $this->makePanel(UserPanel::class);

        $panel->userComponent = 'nonexistent';

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Toolbar items must be an array.',
        );

        $first = $items[0] ?? self::fail('Expected one toolbar item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            'User',
            $first['label'] ?? null,
            "Guest chip label must be 'User'.",
        );
        self::assertSame(
            'Guest',
            $first['value'] ?? null,
            "Guest chip value must be 'Guest'.",
        );
    }

    public function testGetToolbarItemsRendersInfoForMainUser(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new Identity(1));

        $this->setInaccessibleProperty(
            $panel,
            'data',
            ['id' => 42],
        );

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Toolbar items must be an array.',
        );

        $first = $items[0] ?? self::fail('Expected one toolbar item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            'info',
            $first['status'] ?? null,
            'Main user must carry the info status.',
        );
        self::assertSame(
            '42',
            $first['value'] ?? null,
            'Value must echo the captured id.',
        );
    }

    public function testGetToolbarItemsRendersWarningForSwitchedUser(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new Identity(1));

        $userSwitch = $panel->userSwitch ?? self::fail('UserSwitch must be wired.');

        Yii::$app->session->set('main_user', 99);

        $this->setInaccessibleProperty(
            $userSwitch,
            'mainUser',
            null,
        );
        $this->setInaccessibleProperty(
            $panel,
            'data',
            ['id' => 1],
        );

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Toolbar items must be an array.',
        );

        $first = $items[0] ?? self::fail('Expected one toolbar item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            'warning',
            $first['status'] ?? null,
            'Switched user must carry the warning status.',
        );
        self::assertSame(
            'User switching',
            $first['label'] ?? null,
            'Label must mark the switch state.',
        );
    }

    public function testGetToolbarItemsStringifiesNonScalarId(): void
    {
        $panel = $this->makePanel(UserPanel::class);

        $panel->userComponent = 'nonexistent';

        $this->setInaccessibleProperty(
            $panel,
            'data',
            ['id' => ['nested' => 'value']],
        );

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Toolbar items must be an array.',
        );

        $first = $items[0] ?? self::fail('Expected one toolbar item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );

        $value = $first['value'] ?? null;

        self::assertIsString(
            $value,
            'Value must be a string.',
        );
        self::assertStringContainsString(
            "'nested'",
            $value,
            'Non-scalar id must be dumped through VarDumper.',
        );
    }

    public function testGetUserDataProviderReturnsProvider(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new Identity(1));

        $panel->filterModel = new SearchableFilterModel();


        self::assertSame(
            0,
            $panel->getUserDataProvider()->getCount(),
            'Empty provider must report zero count.',
        );
    }

    public function testGetUserReturnsConfiguredUserInstance(): void
    {
        $panel = $this->makePanel(UserPanel::class);

        $user = new User(['identityClass' => Identity::class]);

        $panel->userComponent = $user;

        self::assertSame(
            $user,
            $panel->getUser(),
            'Configured User instance must round-trip.',
        );
    }

    public function testGetUserReturnsNullWhenComponentIsNotUser(): void
    {
        $panel = $this->makePanel(
            UserPanel::class,
            ['user' => stdClass::class],
        );

        $panel->userComponent = 'user';

        self::assertNull(
            $panel->getUser(),
            "Non-User component must yield 'null'.",
        );
    }

    public function testGetUserReturnsResolvedComponentByString(): void
    {
        $panel = $this->makePanel(
            UserPanel::class,
            [
                'user' => [
                    'class' => User::class,
                    'identityClass' => Identity::class,
                ],
            ],
        );

        self::assertInstanceOf(
            User::class,
            $panel->getUser(),
            'String component must resolve to a User instance.',
        );
    }

    public function testGetUsersFilterModelReturnsConfiguredInstance(): void
    {
        $panel = $this->makePanel(UserPanel::class);

        $filterModel = new SearchableFilterModel();
        $panel->filterModel = $filterModel;

        self::assertSame(
            $filterModel,
            $panel->getUsersFilterModel(),
            'Configured Model instance must round-trip.',
        );
    }

    public function testGetUsersFilterModelReturnsNullForStringFilterModel(): void
    {
        $panel = $this->makePanel(UserPanel::class);

        $panel->filterModel = SearchableFilterModel::class;

        self::assertNull(
            $panel->getUsersFilterModel(),
            "Unresolved string class must yield 'null'.",
        );
    }

    public function testInitDoesNothingWhenDisabled(): void
    {
        $this->mockWebApplication(['components' => ['user' => stdClass::class]]);

        $module = new Module('debug');
        $module->logTarget = new LogTarget($module);

        $panel = new UserPanel(['id' => 'user', 'module' => $module]);

        self::assertNull(
            $panel->userSwitch,
            "UserSwitch must remain 'null' when the panel is disabled.",
        );
    }

    public function testInitDoesNothingWhenUserIsGuest(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'user' => [
                        'class' => User::class,
                        'identityClass' => Identity::class,
                        'enableSession' => false,
                    ],
                ],
            ],
        );

        $module = new Module('debug');
        $module->logTarget = new LogTarget($module);

        $panel = new UserPanel(['id' => 'user', 'module' => $module]);

        self::assertNull(
            $panel->userSwitch,
            "UserSwitch must remain 'null' when the user is a guest.",
        );
    }

    public function testInitFilterModelFallsBackToUserSearchForActiveRecordIdentity(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new ArIdentity());

        self::assertInstanceOf(
            UserSearch::class,
            $panel->filterModel,
            "ActiveRecord identity must fall back to 'UserSearch'.",
        );
    }

    public function testInitFilterModelInstantiatesStringClass(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(
            new Identity(1),
            filterModel: SearchableFilterModel::class,
        );

        self::assertInstanceOf(
            SearchableFilterModel::class,
            $panel->filterModel,
            'String class name must be instantiated.',
        );
    }

    public function testInitFilterModelLeavesModelInstanceUntouched(): void
    {
        $filterModel = new SearchableFilterModel();

        $panel = $this->bootstrapPanelWithIdentity(new Identity(1), filterModel: $filterModel);

        self::assertSame(
            $filterModel,
            $panel->filterModel,
            'Pre-built Model instance must round-trip unchanged.',
        );
    }

    public function testIsEnabledReturnsFalseWhenUserComponentMissing(): void
    {
        $panel = $this->makePanel(UserPanel::class);

        $panel->userComponent = 'nonexistent';

        self::assertFalse(
            $panel->isEnabled(),
            "Missing user component must collapse to 'false'.",
        );
    }

    public function testIsEnabledReturnsTrueWhenUserComponentResolves(): void
    {
        $panel = $this->makePanel(
            UserPanel::class,
            [
                'user' => [
                    'class' => User::class,
                    'identityClass' => Identity::class,
                ],
            ],
        );

        self::assertTrue(
            $panel->isEnabled(),
            "Resolvable user component must yield 'true'.",
        );
    }

    public function testSaveCapturesIdentityAttributesAndLabelsForModelIdentity(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new ModelIdentity());

        $saved = $panel->save();

        self::assertNotNull(
            $saved,
            'Identity save must succeed.',
        );
        self::assertSame(
            1,
            $saved['id'],
            'Identity id must round-trip.',
        );
        self::assertSame(
            ['id', 'username'],
            array_column($saved['attributes'] ?? [], 'attribute'),
            'Model identity must surface attribute labels.',
        );
    }

    public function testSaveCapturesIdentityForNonModelIdentity(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new Identity(7));

        $saved = $panel->save();

        self::assertNotNull(
            $saved,
            'Identity save must succeed.',
        );
        self::assertSame(
            7,
            $saved['id'],
            'Identity id must round-trip.',
        );
        self::assertNull(
            $saved['attributes'],
            'Non-Model identity must skip attribute labels.',
        );
    }

    public function testSaveIgnoresAuthManagerMisconfiguration(): void
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
        $panel = new UserPanel(['id' => 'user', 'module' => $module]);

        $saved = $panel->save();

        self::assertNotNull(
            $saved,
            'Save must complete despite missing auth manager.',
        );
        self::assertNull(
            $saved['rolesProvider'],
            "Roles provider must stay 'null' on auth manager failure.",
        );
        self::assertNull(
            $saved['permissionsProvider'],
            "Permissions provider must stay 'null' on auth manager failure.",
        );
    }

    public function testSavePopulatesRbacProvidersWhenAuthManagerWired(): void
    {
        $role = new Role();

        $role->name = 'admin';
        $role->description = 'Administrator';
        $role->createdAt = 1;
        $role->updatedAt = 2;

        $permission = new Permission();

        $permission->name = 'manage';
        $permission->description = 'Manage';
        $permission->createdAt = 3;
        $permission->updatedAt = 4;

        $authManager = self::createStub(BaseManager::class);

        $authManager
            ->method('getRolesByUser')
            ->willReturn([$role->name => $role]);
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
        $panel = new UserPanel(['id' => 'user', 'module' => $module]);

        $saved = $panel->save();

        self::assertNotNull(
            $saved,
            'Save must complete.',
        );
        self::assertInstanceOf(
            ArrayDataProvider::class,
            $saved['rolesProvider'],
            'Roles provider must surface.',
        );
        self::assertInstanceOf(
            ArrayDataProvider::class,
            $saved['permissionsProvider'],
            'Permissions provider must surface.',
        );
    }

    public function testSaveReturnsNullWhenNoIdentity(): void
    {
        $panel = $this->makePanel(
            UserPanel::class,
            [
                'user' => [
                    'class' => User::class,
                    'identityClass' => Identity::class,
                ],
            ],
        );

        self::assertNull(
            $panel->save(),
            "Guest must yield a 'null' save payload.",
        );
    }

    public function testSaveReturnsNullWhenNoUserComponent(): void
    {
        $panel = $this->makePanel(UserPanel::class);

        $panel->userComponent = 'nonexistent';

        self::assertNull(
            $panel->save(),
            "Missing user component must yield a 'null' save payload.",
        );
    }

    public function testThrowInvalidConfigExceptionWhenAddAccessRulesHasNoModule(): void
    {
        $panel = $this->makePanel(UserPanel::class);

        $panel->module = null;

        $panel->userSwitch = new UserSwitch();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'Unable to configure user switching without a debug module.',
        );

        $this->invoke($panel, 'addAccessRules');
    }

    public function testThrowInvalidConfigExceptionWhenFilterModelDoesNotImplementUserSearchInterface(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new Identity(1));

        $panel->filterModel = new NoSearchFilterModel();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'User filter model must implement ' . UserSearchInterface::class . '.',
        );

        $panel->getUserDataProvider();
    }

    public function testThrowInvalidConfigExceptionWhenInitFilterModelStringIsNotUserSearchInterface(): void
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

        Yii::$app->user->login(new Identity(1));

        $module = new Module('debug');
        $module->logTarget = new LogTarget($module);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'User filter model must implement ' . UserSearchInterface::class . '.',
        );

        new UserPanel(['id' => 'user', 'module' => $module, 'filterModel' => stdClass::class]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
    }

    /**
     * Builds a {@see UserPanel} wired to a guest user, with the debug module fully bootstrapped so behaviors attach.
     */
    private function bootstrapPanelWithGuest(): UserPanel
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'user' => [
                        'class' => User::class,
                        'identityClass' => Identity::class,
                        'enableSession' => false,
                    ],
                ],
            ],
        );

        $module = new Module('debug');
        $module->logTarget = new LogTarget($module);
        $panel = new UserPanel(['id' => 'user', 'module' => $module]);

        $panel->userSwitch = new UserSwitch();

        return $panel;
    }

    /**
     * Builds a {@see UserPanel} wired to a logged-in identity, the debug module bootstrapped with the user controller.
     *
     * @param Model|string|null $filterModel Optional filter model passed to the panel constructor.
     */
    private function bootstrapPanelWithIdentity(
        IdentityInterface $identity,
        string|Model|null $filterModel = null,
    ): UserPanel {
        $assetPath = dirname(__DIR__, 2) . '/runtime/assets';

        @mkdir($assetPath, 0o777, true);

        $this->mockWebApplication(
            [
                'components' => [
                    'user' => [
                        'class' => User::class,
                        'identityClass' => $identity::class,
                    ],
                    'assetManager' => [
                        'basePath' => $assetPath,
                        'baseUrl' => '/assets',
                    ],
                ],
            ],
        );

        Yii::$app->user->login($identity);

        $module = new Module('debug');
        $module->logTarget = new LogTarget($module);

        $config = ['id' => 'user', 'module' => $module];

        if ($filterModel !== null) {
            $config['filterModel'] = $filterModel;
        }

        return new UserPanel($config);
    }
}
