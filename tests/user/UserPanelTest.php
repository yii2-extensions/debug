<?php

declare(strict_types=1);

namespace yii\debug\tests\user;

use PHPForge\Debug\Panel\User\{UserRbacRow, UserSnapshot};
use PHPUnit\Framework\Attributes\Group;
use stdClass;
use Yii;
use yii\base\{Action, InvalidConfigException, Model};
use yii\data\{ArrayDataProvider, DataProviderInterface};
use yii\debug\exception\Message;
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
    RequiredOptionAction,
    SearchableFilterModel,
};
use yii\debug\tests\support\TestCase;
use yii\filters\{AccessControl, AccessRule};
use yii\rbac\{Permission, Role};
use yii\web\{Controller, IdentityInterface, User};

/**
 * Unit tests for {@see UserPanel} covering the user-switch
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
            'An incompatible filter model must be rejected.',
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

    public function testCanSwitchUserPassesOwningModuleToAccessRule(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new Identity(1));

        $module = $panel->module ?? self::fail('Module must be wired.');

        $panel->ruleUserSwitch = [
            'allow' => true,
            'matchCallback' => static fn(AccessRule $rule, Action $action): bool => $action->getModule() === $module,
        ];

        self::assertTrue(
            $panel->canSwitchUser(),
            'Access rule must receive the owning module in the action.',
        );
    }

    public function testCanSwitchUserPreservesConfiguredActionProperties(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new Identity(1));

        $module = $panel->module ?? self::fail('Module must be wired.');

        $panel->ruleUserSwitch = ['allow' => true];

        // A stripped config would drop 'requiredOption' and make the action throw on instantiation.
        $module->actionMap['set-identity'] = [
            'class' => RequiredOptionAction::class,
            'requiredOption' => 'configured',
        ];

        self::assertTrue(
            $panel->canSwitchUser(),
            "Array-shaped 'set-identity' config must apply configured properties.",
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

    public function testCanSwitchUserReturnsFalseWhenMappedActionIsNotAnAction(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new Identity(1));

        $module = $panel->module ?? self::fail('Module must be wired.');

        $panel->ruleUserSwitch = ['allow' => true];

        $module->actionMap['set-identity'] = stdClass::class;

        self::assertFalse(
            $panel->canSwitchUser(),
            'Non-action class in the action map must deny switching.',
        );
    }

    public function testCanSwitchUserReturnsFalseWhenSetIdentityActionMissing(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new Identity(1));

        $module = $panel->module ?? self::fail('Module must be wired.');

        $panel->ruleUserSwitch = ['allow' => true];

        unset($module->actionMap['set-identity']);

        self::assertFalse(
            $panel->canSwitchUser(),
            "Missing 'set-identity' entry must deny switching.",
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

    public function testGetDetailMakesUserSwitchRowsFocusableAndExplicitlyNamed(): void
    {
        $filterModel = new class extends Model implements UserSearchInterface {
            public int|string|null $id = null;

            public function formName(): string
            {
                return 'User';
            }

            public function search(array $params): DataProviderInterface
            {
                return new ArrayDataProvider(
                    [
                        'allModels' => [['id' => 42]],
                        'key' => 'id',
                        'pagination' => false,
                        'sort' => false,
                    ],
                );
            }
        };

        $panel = $this->bootstrapPanelWithIdentity(new ModelIdentity(), filterModel: $filterModel);

        self::assertNotNull($panel->module, 'Module must be wired.');

        Yii::$app->controller = new Controller('debug', $panel->module);

        $panel->filterColumns = [['attribute' => 'id']];
        $panel->ruleUserSwitch = ['allow' => true];

        $this->hydratePanel(
            $panel,
            UserSnapshot::capture(
                [
                    'id' => 1,
                    'identity' => ['id' => "'1'"],
                    'attributes' => [
                        [
                            'attribute' => 'id',
                            'label' => 'Id',
                        ],
                    ],
                ],
            ),
        );

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'aria-label="Switch to user 42"',
            $html,
            'Each switch result row must expose the action and target user to assistive technology.',
        );
        self::assertStringContainsString(
            'tabindex="0"',
            $html,
            'Each switch result row must participate in sequential keyboard navigation.',
        );
    }

    public function testGetDetailRendersGuestPlaceholderWhenIdentityMissing(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new ModelIdentity());

        $this->hydratePanel(
            $panel,
            UserSnapshot::capture(['id' => null, 'identity' => null]),
        );

        $detail = $panel->getDetail();

        self::assertStringContainsString(
            'yii-debug-empty-state',
            $detail,
            'Guest state must render the empty-state card.',
        );
        self::assertStringContainsString(
            'No user authenticated in this request',
            $detail,
            'Card headline must describe the guest state.',
        );
    }

    public function testGetDetailRendersIdentityView(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new ModelIdentity());

        $this->hydratePanel(
            $panel,
            UserSnapshot::capture(
                [
                    'id' => 1,
                    'identity' => ['id' => "'1'", 'username' => "'wilmer'"],
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
                    'rolesProvider' => null,
                    'permissionsProvider' => null,
                ],
            ),
        );

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

        $this->hydratePanel(
            $panel,
            UserSnapshot::capture(
                [
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
                ],
            ),
        );

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

        $this->hydratePanel(
            $panel,
            UserSnapshot::capture(
                [
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
                    'roles' => [
                        [
                            'name' => $role->name,
                            'description' => $role->description,
                            'ruleName' => null,
                            'data' => 'null',
                            'createdAt' => null,
                            'updatedAt' => null,
                        ],
                    ],
                    'permissions' => [
                        [
                            'name' => $permission->name,
                            'description' => $permission->description,
                            'ruleName' => null,
                            'data' => 'null',
                            'createdAt' => null,
                            'updatedAt' => null,
                        ],
                    ],
                ],
            ),
        );

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

    public function testGetPermissionsProviderHydratesUserRbacRowModels(): void
    {
        $panel = $this->makePanel(UserPanel::class);

        $this->hydratePanel(
            $panel,
            UserSnapshot::capture(
                [
                    'id' => 1,
                    'permissions' => [
                        [
                            'name' => 'manage',
                            'description' => 'Manage',
                            'ruleName' => 'isManager',
                            'data' => 'null',
                            'createdAt' => 1_700_000_000,
                            'updatedAt' => 1_700_000_001,
                        ],
                    ],
                ],
            ),
        );

        $provider = $panel->getPermissionsProvider();

        self::assertNotNull(
            $provider,
            'Snapshot with permissions must yield a provider.',
        );

        $models = $provider->getModels();

        self::assertContainsOnlyInstancesOf(
            UserRbacRow::class,
            $models,
            'Models must be typed rows.',
        );

        $row = $models[0] ?? null;

        self::assertInstanceOf(
            UserRbacRow::class,
            $row,
            'First row must exist.',
        );
        self::assertSame(
            'manage',
            $row->name,
            'Row name must survive hydration.',
        );
        self::assertSame(
            'isManager',
            $row->ruleName,
            'Rule name must survive hydration.',
        );
        self::assertSame(
            1_700_000_000,
            $row->createdAt,
            'Created-at must survive hydration.',
        );
    }

    public function testGetRolesProviderHydratesUserRbacRowModels(): void
    {
        $panel = $this->makePanel(UserPanel::class);

        $this->hydratePanel(
            $panel,
            UserSnapshot::capture(
                [
                    'id' => 1,
                    'roles' => [
                        [
                            'name' => 'admin',
                            'description' => 'Administrator',
                            'ruleName' => null,
                            'data' => 'null',
                            'createdAt' => null,
                            'updatedAt' => null,
                        ],
                        'not-an-array',
                    ],
                ],
            ),
        );

        $provider = $panel->getRolesProvider();

        self::assertNotNull(
            $provider,
            'Snapshot with roles must yield a provider.',
        );

        $models = $provider->getModels();

        self::assertContainsOnlyInstancesOf(
            UserRbacRow::class,
            $models,
            'Models must be typed rows.',
        );
        self::assertCount(
            2,
            $models,
            'Malformed entries must hydrate as empty rows, not vanish.',
        );

        $first = $models[0] ?? null;
        $second = $models[1] ?? null;

        self::assertInstanceOf(
            UserRbacRow::class,
            $first,
            'First row must exist.',
        );
        self::assertInstanceOf(
            UserRbacRow::class,
            $second,
            'Second row must exist.',
        );
        self::assertSame(
            'admin',
            $first->name,
            'Row name must survive hydration.',
        );
        self::assertSame(
            '',
            $first->ruleName,
            '`null` rule name must collapse to an empty `string`.',
        );
        self::assertNull(
            $first->createdAt,
            '`null` created-at must stay `null`.',
        );
        self::assertSame(
            '',
            $second->name,
            'Malformed entry must yield an empty row.',
        );
    }

    public function testGetRolesProviderReturnsNullWhenSnapshotLacksRoles(): void
    {
        $panel = $this->makePanel(UserPanel::class);

        $this->hydratePanel(
            $panel,
            UserSnapshot::capture(['id' => 1]),
        );

        self::assertNull(
            $panel->getRolesProvider(),
            'Missing roles key must yield `null`.',
        );
        self::assertNull(
            $panel->getPermissionsProvider(),
            'Missing permissions key must yield `null`.',
        );
    }

    public function testGetToolbarItemsRendersGuestWhenNoIdInData(): void
    {
        $panel = $this->makePanel(
            UserPanel::class,
        );

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
        self::assertArrayNotHasKey(
            'label',
            $first,
            'Panel title must identify the Guest chip without duplication.',
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

        $this->hydratePanel(
            $panel,
            UserSnapshot::capture(['id' => 42]),
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
        self::assertArrayNotHasKey(
            'label',
            $first,
            'Panel title must identify the main-user chip without duplication.',
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
        $this->hydratePanel(
            $panel,
            UserSnapshot::capture(['id' => 1]),
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
            'switching',
            $first['label'] ?? null,
            'Label must add only the non-duplicated switch state.',
        );
    }

    public function testGetToolbarItemsStringifiesNonScalarId(): void
    {
        $panel = $this->makePanel(UserPanel::class);

        $panel->userComponent = 'nonexistent';

        $this->hydratePanel(
            $panel,
            UserSnapshot::capture(['id' => ['nested' => 'value']]),
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
        $panel = $this->makePanel(
            UserPanel::class,
        );

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
        $panel = $this->makePanel(
            UserPanel::class,
        );

        $panel->filterModel = SearchableFilterModel::class;

        self::assertNull(
            $panel->getUsersFilterModel(),
            "Unresolved string class must yield 'null'.",
        );
    }

    public function testInitAttachesTheSwitchAccessGuardForGuest(): void
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

        $module->detachBehavior('access_debug');

        $panel = new UserPanel(['id' => 'user', 'module' => $module]);

        $behavior = $module->getBehavior('access_debug');

        self::assertInstanceOf(
            AccessControl::class,
            $behavior,
            'AccessControl behavior must attach to the module.',
        );
        self::assertSame(
            ['set-identity', 'reset-identity'],
            $behavior->only,
            'Switch actions must be gated by the behavior.',
        );
        self::assertCount(
            1,
            $behavior->rules,
            'AccessControl behavior must have one rule for the switch actions.',
        );

        $rule = $behavior->rules[0] ?? null;

        self::assertInstanceOf(
            AccessRule::class,
            $rule,
            'AccessControl rule must be an AccessRule instance.',
        );
        self::assertSame(
            ['set-identity', 'reset-identity'],
            $rule->actions,
            'AccessControl rule must have the correct actions.',
        );
        self::assertNull(
            $panel->filterModel,
            'Guest must not get the user-search filter.',
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

    public function testInitFilterModelKeepsInstantiatedStringClassForActiveRecordIdentity(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(
            new ArIdentity(),
            filterModel: SearchableFilterModel::class,
        );

        self::assertInstanceOf(
            SearchableFilterModel::class,
            $panel->filterModel,
            'ActiveRecord identity must keep the instantiated string class.',
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

    public function testInitFilterModelLeavesModelInstanceUntouchedForActiveRecordIdentity(): void
    {
        $filterModel = new SearchableFilterModel();

        $panel = $this->bootstrapPanelWithIdentity(new ArIdentity(), filterModel: $filterModel);

        self::assertSame(
            $filterModel,
            $panel->filterModel,
            'Pre-built Model instance must round-trip unchanged for ActiveRecord identity.',
        );
    }

    public function testInitPassesConfiguredUserComponentToUserSwitch(): void
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

        $customUser = new User(
            [
                'identityClass' => Identity::class,
                'enableSession' => false,
            ],
        );

        $module = new Module('debug');
        $module->logTarget = new LogTarget($module);
        $panel = new UserPanel(['id' => 'user', 'module' => $module, 'userComponent' => $customUser]);

        $userSwitch = $panel->userSwitch;

        self::assertNotNull(
            $userSwitch,
            'User switch component must be instantiated.',
        );
        self::assertSame(
            $customUser,
            $userSwitch->getUser(),
            'User switch component must receive the configured user component.',
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

    public function testModuleBoundAttachesTheSwitchAccessGuardForPrebuiltPanelInstance(): void
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

        $panel = new UserPanel();

        self::assertNull(
            $panel->module,
            'Prebuilt panel must start without a module reference.',
        );

        $module = new Module('debug', null, ['panels' => ['user' => $panel]]);

        self::assertSame(
            $module,
            $panel->module,
            'Module must bind itself onto the prebuilt instance.',
        );
        self::assertNotNull(
            $module->getBehavior('access_debug'),
            'Switch actions must stay gated for a prebuilt instance.',
        );
    }

    public function testModuleBoundSkipsTheAccessGuardWhenPanelIsDisabled(): void
    {
        $this->mockWebApplication(['components' => ['user' => stdClass::class]]);

        $panel = new UserPanel();
        $module = new Module('debug', null, ['panels' => ['user' => $panel]]);

        self::assertNull(
            $module->getBehavior('access_debug'),
            'No guard must attach without a user-switch model.',
        );
        self::assertArrayNotHasKey(
            'user',
            $module->panels,
            'Disabled panel must be dropped.',
        );
    }

    public function testThrowInvalidConfigExceptionWhenAddAccessRulesHasNoModule(): void
    {
        $panel = $this->makePanel(UserPanel::class);

        $panel->module = null;

        $panel->userSwitch = new UserSwitch();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::USER_SWITCH_MODULE_REQUIRED->getMessage(),
        );

        $this->invoke($panel, 'addAccessRules');
    }

    public function testThrowInvalidConfigExceptionWhenFilterModelDoesNotImplementUserSearchInterface(): void
    {
        $panel = $this->bootstrapPanelWithIdentity(new Identity(1));

        $panel->filterModel = new NoSearchFilterModel();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::USER_FILTER_MODEL_INVALID->getMessage(UserSearchInterface::class),
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
            Message::USER_FILTER_MODEL_INVALID->getMessage(UserSearchInterface::class),
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
