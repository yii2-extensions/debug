<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Throwable;
use Yii;
use yii\base\{InvalidConfigException, Model};
use yii\data\{ArrayDataProvider, DataProviderInterface};
use yii\db\ActiveRecord;
use yii\debug\controllers\UserController;
use yii\debug\models\search\{UserSearch, UserSearchInterface};
use yii\debug\models\UserSwitch;
use yii\debug\Panel;
use yii\di\Instance;
use yii\filters\{AccessControl, AccessRule};
use yii\helpers\VarDumper;
use yii\rbac\{BaseManager, Item};
use yii\web\{IdentityInterface, User};

use function class_exists;
use function get_object_vars;
use function is_array;
use function is_scalar;
use function is_string;

/**
 * Captures the authenticated identity and renders it in the User panel, optionally allowing the developer to switch to
 * another user.
 *
 * Captures the identity's attributes, RBAC roles, and permissions; surfaces them through the detail view with `Reveal`
 * buttons on sensitive fields; and (when the configured access rule allows) lists candidate identities in a GridView so
 * the developer can impersonate one with a single click.
 *
 * @extends Panel<array{
 *   id: int|string|null,
 *   identity: array<string, string>,
 *   attributes: array<int, array{attribute: string, label: string}>|null,
 *   rolesProvider: ArrayDataProvider|null,
 *   permissionsProvider: ArrayDataProvider|null,
 * }>
 */
class UserPanel extends Panel
{
    /**
     * Display name shown in the panel header and the toolbar chip.
     */
    public string $displayName = 'User';
    /**
     * @var array<int|string, mixed> GridView column definitions for the user-switch table.
     */
    public array $filterColumns = [
        [
            'attribute' => 'id',
            'headerOptions' => ['class' => 'yii-debug-col-userswitch-id'],
            'contentOptions' => ['class' => 'yii-debug-col-userswitch-id'],
        ],
        [
            'attribute' => 'username',
            'headerOptions' => ['class' => 'yii-debug-col-userswitch-username'],
        ],
        [
            'attribute' => 'email',
            'headerOptions' => ['class' => 'yii-debug-col-userswitch-email'],
        ],
        [
            'attribute' => 'status',
            'headerOptions' => ['class' => 'yii-debug-col-userswitch-status'],
            'contentOptions' => ['class' => 'yii-debug-col-userswitch-status'],
        ],
        [
            'attribute' => 'created_at',
            'format' => ['datetime', 'php:Y-m-d H:i'],
            'headerOptions' => ['class' => 'yii-debug-col-userswitch-timestamp'],
            'contentOptions' => ['class' => 'yii-debug-col-userswitch-timestamp'],
        ],
        [
            'attribute' => 'updated_at',
            'format' => ['datetime', 'php:Y-m-d H:i'],
            'headerOptions' => ['class' => 'yii-debug-col-userswitch-timestamp'],
            'contentOptions' => ['class' => 'yii-debug-col-userswitch-timestamp'],
        ],
    ];
    /**
     * Filter model that powers the user-switch GridView; can be a class-name string, a model instance, or `null` to
     * disable the search affordance.
     */
    public string|Model|null $filterModel = null;
    /**
     * @var array<string, mixed> Access-rule definition that decides who can switch user identity.
     */
    public array $ruleUserSwitch = [
        'allow' => false,
    ];
    /**
     * Component id of the user component, or a {@see User} instance to operate on directly.
     */
    public string|User $userComponent = 'user';
    /**
     * User-switching model bound on {@see init()} once the panel resolves a non-guest identity.
     */
    public UserSwitch|null $userSwitch = null;

    /**
     * Returns whether the user-switch search affordance is available (the filter model exposes a `search()` method).
     */
    public function canSearchUsers(): bool
    {
        return $this->getSearchableFilterModel() !== null;
    }

    /**
     * Returns whether the main (pre-switch) user is allowed to switch identities under {@see $ruleUserSwitch}.
     *
     * @throws InvalidConfigException When the debug module or the user component cannot be resolved.
     */
    public function canSwitchUser(): bool
    {
        $module = $this->module;

        $user = $this->getUser();

        $userSwitch = $this->userSwitch;

        if ($module === null || $user === null || $user->isGuest || $userSwitch === null) {
            return false;
        }

        $rule = new AccessRule($this->ruleUserSwitch);

        $controller = $module->createController('user');

        if (!is_array($controller) || !$controller[0] instanceof UserController) {
            return false;
        }

        $action = $controller[0]->createAction('set-identity');

        if ($action === null) {
            return false;
        }

        return $rule->allows($action, $userSwitch->getMainUser(), Yii::$app->request) === true;
    }

    /**
     * Renders the detail view with the identity card and the user-switch GridView.
     */
    public function getDetail(): string
    {
        return Yii::$app->view->render(
            'panels/user/detail',
            ['panel' => $this],
            $this,
        );
    }

    /**
     * Returns the panel display name (configurable via {@see $displayName}).
     */
    public function getName(): string
    {
        return $this->displayName;
    }

    /**
     * Renders the toolbar summary chip.
     */
    public function getSummary(): string
    {
        return Yii::$app->view->render(
            'panels/user/summary',
            ['panel' => $this],
            $this,
        );
    }

    /**
     * Returns the toolbar icon name.
     */
    public function getToolbarIcon(): string
    {
        return 'user';
    }

    /**
     * Returns the user component bound to this panel, or `null` when the configured component id does not resolve to
     * a {@see User} instance.
     *
     * @throws InvalidConfigException When the configured component cannot be retrieved from the application.
     */
    public function getUser(): User|null
    {
        if ($this->userComponent instanceof User) {
            return $this->userComponent;
        }

        $user = Yii::$app->get($this->userComponent, false);

        return $user instanceof User ? $user : null;
    }

    /**
     * Returns the data provider that backs the user-switch GridView.
     *
     * @throws InvalidConfigException When the filter model does not implement {@see UserSearchInterface}.
     */
    public function getUserDataProvider(): DataProviderInterface
    {
        $filterModel = $this->getSearchableFilterModel();

        if ($filterModel === null) {
            throw new InvalidConfigException(
                'User filter model must implement ' . UserSearchInterface::class . '.',
            );
        }

        return $filterModel->search(Yii::$app->request->getQueryParams());
    }

    /**
     * Returns the filter model instance for the GridView, or `null` when the filter model is not configured as an
     * instance.
     */
    public function getUsersFilterModel(): Model|null
    {
        return $this->filterModel instanceof Model ? $this->filterModel : null;
    }

    /**
     * Wires the user-switch model, the access rules, and the filter model when the user component resolves to a
     * non-guest identity.
     *
     * @throws InvalidConfigException When the user component cannot be resolved or the filter model cannot be created.
     */
    public function init(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $user = $this->getUser();

        if ($user === null || $user->isGuest) {
            return;
        }

        $this->userSwitch = new UserSwitch(['userComponent' => $this->userComponent]);

        $this->addAccessRules();
        $this->initFilterModel($user);
    }

    /**
     * Returns whether the user component is resolvable; the panel is harmless on apps with no user component.
     */
    public function isEnabled(): bool
    {
        try {
            return $this->getUser() !== null;
        } catch (InvalidConfigException) {
            return false;
        }
    }

    /**
     * Snapshots the identity attributes, the RBAC roles, and the permissions for the active user.
     *
     * Returns `null` when there is no resolvable identity, so the detail view falls back to its empty state.
     *
     * @return array{
     *   id: int|string|null,
     *   identity: array<string, string>,
     *   attributes: array<int, array{attribute: string, label: string}>|null,
     *   rolesProvider: ArrayDataProvider|null,
     *   permissionsProvider: ArrayDataProvider|null
     * }|null Captured payload consumed by the detail view, or `null` when no identity is bound.
     */
    public function save(): array|null
    {
        $user = $this->getUser();

        if ($user === null || !$user->identity instanceof IdentityInterface) {
            return null;
        }

        $identity = $user->identity;

        $userId = $user->getId();

        $rolesProvider = null;
        $permissionsProvider = null;

        $module = $this->module;

        if ($module !== null && $userId !== null) {
            try {
                $authManager = Instance::ensure($module->authManager, BaseManager::class);

                $rolesProvider = new ArrayDataProvider(
                    [
                        'allModels' => $this->normalizeRbacItems($authManager->getRolesByUser($userId)),
                    ],
                );

                $permissionsProvider = new ArrayDataProvider(
                    [
                        'allModels' => $this->normalizeRbacItems($authManager->getPermissionsByUser($userId)),
                    ],
                );
            } catch (Throwable) {
                // Ignore auth manager misconfiguration so the identity panel remains available.
            }
        }

        $rawIdentityData = $this->identityData($identity);

        $identityData = [];

        foreach ($rawIdentityData as $key => $value) {
            $identityData[$key] = VarDumper::dumpAsString($value);
        }

        // If the identity is a model, let it specify the attribute labels
        if ($identity instanceof Model) {
            $attributes = [];

            foreach (array_keys($identityData) as $attribute) {
                $attributes[] = [
                    'attribute' => $attribute,
                    'label' => $identity->getAttributeLabel($attribute),
                ];
            }
        } else {
            // Let the DetailView widget figure the labels out
            $attributes = null;
        }

        return [
            'id' => $identity->getId(),
            'identity' => $identityData,
            'attributes' => $attributes,
            'rolesProvider' => $rolesProvider,
            'permissionsProvider' => $permissionsProvider,
        ];
    }

    /**
     * Returns the value when it is already a string, otherwise renders it with {@see VarDumper::export()}.
     */
    protected function dataToString(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        return VarDumper::export($data);
    }

    /**
     * Builds the toolbar item with the active identity id, switching the chip to a `warning` tone when impersonation
     * is active.
     *
     * @return array<int, array<string, mixed>> Single-element list with the user chip.
     */
    protected function getToolbarItems(): array
    {
        $user = $this->getUser();

        $data = is_array($this->data) ? $this->data : [];

        $id = $data['id'] ?? null;

        $idLabel = is_scalar($id) ? (string) $id : VarDumper::dumpAsString($id);

        if ($id === null) {
            $item = [
                'label' => 'User',
                'value' => 'Guest',
            ];
        } elseif ($user === null || $user->isGuest || $this->userSwitch === null || $this->userSwitch->isMainUser()) {
            $item = [
                'label' => $this->getName(),
                'status' => 'info',
                'value' => $idLabel,
            ];
        } else {
            $item = [
                'label' => $this->getName() . ' switching',
                'status' => 'warning',
                'value' => $idLabel,
            ];
        }

        return [$item];
    }

    /**
     * Returns the identity attributes as a string-keyed map suitable for {@see \yii\widgets\DetailView::$model}.
     *
     * Reads {@see Model::getAttributes()} when the identity is a {@see Model}; otherwise falls back to
     * {@see get_object_vars()} on the identity object.
     *
     * @param IdentityInterface $identity Active identity object.
     *
     * @return array<string, mixed> Attribute map ready to feed the detail view.
     */
    protected function identityData(IdentityInterface $identity): array
    {
        if ($identity instanceof Model) {
            return self::normalizeStringKeyArray($identity->getAttributes());
        }

        return self::normalizeStringKeyArray(get_object_vars($identity));
    }

    /**
     * Attaches the {@see AccessControl} behavior to the debug module, scoped to the user-switch controller and the
     * debug default controller.
     *
     * The behavior evaluates the rule against the main user (the identity captured before any switch), so a switched
     * impersonator never accidentally grants itself further access.
     *
     * @throws InvalidConfigException When the debug module or the user-switch model is not configured.
     */
    private function addAccessRules(): void
    {
        $module = $this->module;
        $userSwitch = $this->userSwitch;

        if ($module === null || $userSwitch === null) {
            throw new InvalidConfigException(
                'Unable to configure user switching without a debug module.',
            );
        }

        $userControllerRoute = $module->getUniqueId() . '/user';

        $this->ruleUserSwitch['controllers'] = [$userControllerRoute];

        $module->attachBehavior(
            'access_debug',
            [
                'class' => AccessControl::class,
                'only' => [
                    $userControllerRoute,
                    $module->getUniqueId() . '/default',
                ],
                'user' => $userSwitch->getMainUser(),
                'rules' => [$this->ruleUserSwitch],
            ],
        );
    }

    /**
     * Returns the configured filter model when it implements {@see UserSearchInterface}, `null` otherwise.
     */
    private function getSearchableFilterModel(): UserSearchInterface|null
    {
        return $this->filterModel instanceof UserSearchInterface ? $this->filterModel : null;
    }

    /**
     * Resolves {@see $filterModel} to a usable {@see UserSearchInterface} instance.
     *
     * Instantiates the configured class name when given a string; leaves an already-instantiated model alone;
     * otherwise, falls back to the bundled {@see UserSearch} when the application identity is an {@see ActiveRecord}.
     *
     * @param User $user Resolved user component.
     *
     * @throws InvalidConfigException When the configured filter-model class does not implement
     * {@see UserSearchInterface}.
     */
    private function initFilterModel(User $user): void
    {
        $filterModel = $this->filterModel;

        if (is_string($filterModel) && class_exists($filterModel)) {
            $model = Yii::createObject($filterModel);

            if (!$model instanceof Model || !$model instanceof UserSearchInterface) {
                throw new InvalidConfigException(
                    'User filter model must implement ' . UserSearchInterface::class . '.',
                );
            }

            $this->filterModel = $model;

            return;
        }

        if ($filterModel instanceof Model) {
            return;
        }

        $identityClass = $user->identityClass;

        if (is_subclass_of($identityClass, ActiveRecord::class)) {
            $this->filterModel = new UserSearch();
        }
    }

    /**
     * Narrows the RBAC items returned by the auth manager into typed rows suitable for an {@see ArrayDataProvider}.
     *
     * @param array<int|string, Item> $items RBAC items indexed by item name.
     *
     * @return array<int, array{
     *   name: string,
     *   description: string,
     *   ruleName: string|null,
     *   data: string,
     *   createdAt: int,
     *   updatedAt: int
     * }> Rows in iteration order.
     */
    private function normalizeRbacItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $normalized[] = [
                'name' => $item->name,
                'description' => $item->description,
                'ruleName' => $item->ruleName,
                'data' => $this->dataToString($item->data),
                'createdAt' => $item->createdAt,
                'updatedAt' => $item->updatedAt,
            ];
        }

        return $normalized;
    }

    /**
     * Stringifies every key of the input array, so the detail view sees a `string => mixed` map.
     *
     * @param array<int|string, mixed> $data Raw identity data.
     *
     * @return array<string, mixed> Same entries with their keys coerced to strings.
     */
    private static function normalizeStringKeyArray(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}
