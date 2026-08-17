<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Panel\User\UserSnapshot;
use Yii;
use yii\base\{Action as BaseAction, InvalidConfigException, Model};
use yii\data\{ArrayDataProvider, DataProviderInterface};
use yii\db\ActiveRecord;
use yii\debug\models\search\{UserSearch, UserSearchInterface};
use yii\debug\models\UserSwitch;
use yii\debug\Panel;
use yii\filters\{AccessControl, AccessRule};
use yii\helpers\VarDumper;
use yii\web\User;

use function class_exists;
use function is_array;
use function is_scalar;
use function is_string;

/**
 * Renders the authenticated identity captured by the User collector, optionally allowing the developer to switch to
 * another user.
 *
 * Presents the identity's attributes, RBAC roles, and permissions through the detail view with `Reveal` buttons on
 * sensitive fields; and (when the configured access rule allows) lists candidate identities in a GridView so the
 * developer can impersonate one with a single click. Data acquisition lives in
 * {@see \yii\debug\collectors\UserCollector}.
 */
class UserPanel extends Panel
{
    protected const string ICON = 'user';

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

    private UserSnapshot|null $snapshot = null;

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

        $actionConfig = $module->actionMap['set-identity'] ?? null;

        if (is_array($actionConfig)) {
            $actionConfig = $actionConfig['class'] ?? null;
        }

        if (!is_string($actionConfig) || !class_exists($actionConfig)) {
            return false;
        }

        $action = Yii::createObject($actionConfig);

        if (!$action instanceof BaseAction) {
            return false;
        }

        $action->id = 'set-identity';
        $action->setModule($module);

        return $rule->allows($action, $userSwitch->getMainUser(), Yii::$app->request) === true;
    }

    /**
     * Renders the detail view with the identity card and the user-switch GridView.
     */
    #[Override]
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
    #[Override]
    public function getName(): string
    {
        return $this->displayName;
    }

    public function getPermissionsProvider(): ArrayDataProvider|null
    {
        return $this->rbacProvider('permissions');
    }

    public function getRolesProvider(): ArrayDataProvider|null
    {
        return $this->rbacProvider('roles');
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getSnapshotData(): array
    {
        return $this->snapshot?->data() ?? [];
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
     * @param array<string, mixed> $payload
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->snapshot = UserSnapshot::fromArray($payload, "$.panels.{$this->id}");
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
    #[Override]
    public function isEnabled(): bool
    {
        try {
            return $this->getUser() !== null;
        } catch (InvalidConfigException) {
            return false;
        }
    }

    /**
     * Builds the toolbar item with the active identity id, switching the chip to a `warning` tone when impersonation
     * is active.
     *
     * @return array<int, array<string, mixed>> Single-element list with the user chip.
     */
    #[Override]
    protected function getToolbarItems(): array
    {
        $user = $this->getUser();

        $data = $this->getSnapshotData();

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

        $this->ruleUserSwitch['actions'] = ['set-identity', 'reset-identity'];

        $module->attachBehavior(
            'access_debug',
            [
                'class' => AccessControl::class,
                'only' => ['set-identity', 'reset-identity'],
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

    private function rbacProvider(string $key): ArrayDataProvider|null
    {
        $rows = $this->getSnapshotData()[$key] ?? null;

        return is_array($rows) ? new ArrayDataProvider(['allModels' => $rows]) : null;
    }
}
