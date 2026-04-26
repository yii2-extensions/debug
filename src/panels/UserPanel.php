<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Throwable;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\data\{ArrayDataProvider, DataProviderInterface};
use yii\db\ActiveRecord;
use yii\debug\controllers\UserController;
use yii\debug\models\search\User as UserSearch;
use yii\debug\models\search\UserSearchInterface;
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
use function is_callable;
use function is_scalar;
use function is_string;
use function method_exists;

/**
 * Debugger panel that collects and displays user data.
 */
class UserPanel extends Panel
{
    /**
     * Display Name of the debug panel.
     */
    public string $displayName = 'User';
    /**
     * @var array<int|string, mixed> Allowed columns for GridView.
     *
     * @see https://www.yiiframework.com/doc-2.0/yii-grid-gridview.html#$columns-detail
     */
    public array $filterColumns = [];
    /**
     * User filter model class name or instance with a search method.
     */
    public string|Model|null $filterModel = null;
    /**
     * @var array<string, mixed> The rule that defines who is allowed to switch user identity.
     *
     * Access Control Filter single rule. Ignore: actions, controllers, verbs.
     * Settable: allow, roles, ips, matchCallback, denyCallback.
     * By default deny for everyone. Recommendation: can allow for administrator or developer (if implement) role:
     * ['allow' => true, 'roles' => ['admin']]
     * @see https://www.yiiframework.com/doc-2.0/guide-security-authorization.html
     */
    public array $ruleUserSwitch = [
        'allow' => false,
    ];
    /**
     * ID of the user component or a user object
     */
    public string|User $userComponent = 'user';
    /**
     * User-switching model.
     */
    public UserSwitch|null $userSwitch = null;

    /**
     * Checks whether user search is available.
     */
    public function canSearchUsers(): bool
    {
        return $this->getSearchableFilterModel() !== null;
    }

    /**
     * Check can main user switch identity.
     *
     * @throws InvalidConfigException if the debug module is not configured properly or the user component is not
     * available.
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

    public function getDetail(): string
    {
        return Yii::$app->view->render('panels/user/detail', ['panel' => $this]);
    }

    public function getName(): string
    {
        return $this->displayName;
    }

    public function getSummary(): string
    {
        return Yii::$app->view->render(
            'panels/user/summary',
            ['panel' => $this],
        );
    }

    public function getToolbarIcon(): string
    {
        return 'user';
    }

    /**
     * @throws InvalidConfigException if the user component is not available or does not return a valid user object.
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
     * Get model for GridView -> DataProvider
     *
     * @throws InvalidConfigException if the filter model is missing or returns an invalid data provider.
     */
    public function getUserDataProvider(): DataProviderInterface
    {
        $filterModel = $this->getSearchableFilterModel();

        if ($filterModel === null) {
            throw new InvalidConfigException('User filter model must be a model with a search method.');
        }

        return $this->searchUsers($filterModel, Yii::$app->request->getQueryParams());
    }

    /**
     * Get model for GridView -> FilterModel
     */
    public function getUsersFilterModel(): Model|null
    {
        return $this->filterModel instanceof Model ? $this->filterModel : null;
    }

    /**
     * @throws InvalidConfigException if the user component is not available or does not return a valid user object, or
     * if the filter model cannot be initialized properly.
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

    public function isEnabled(): bool
    {
        try {
            return $this->getUser() !== null;
        } catch (InvalidConfigException) {
            return false;
        }
    }

    /**
     * @return array{
     *   id: int|string|null,
     *   identity: array<string, string>,
     *   attributes: array<int, array{attribute: string, label: string}>|null,
     *   rolesProvider: ArrayDataProvider|null,
     *   permissionsProvider: ArrayDataProvider|null
     * }|null
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
     * Converts mixed data to string
     */
    protected function dataToString(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        return VarDumper::export($data);
    }

    /**
     * @return array<int, array<string, mixed>>
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
     * Returns the array that should be set on {qsee \yii\widgets\DetailView::model}.
     *
     * @param IdentityInterface $identity if the identity is a model, its attributes will be used to determine the
     * labels for the detail view. Otherwise,
     *
     * @return array<string, mixed>
     */
    protected function identityData(IdentityInterface $identity): array
    {
        if ($identity instanceof Model) {
            return self::normalizeStringKeyArray($identity->getAttributes());
        }

        return self::normalizeStringKeyArray(get_object_vars($identity));
    }

    /**
     * Add ACF rule. AccessControl attach to debug module.
     * Access rule for main user.
     *
     * @throws InvalidConfigException if the debug module is not configured properly or the user component is not
     * available.
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
                'only' => [$userControllerRoute, $module->getUniqueId() . '/default'],
                'user' => $userSwitch->getMainUser(),
                'rules' => [
                    $this->ruleUserSwitch,
                ],
            ],
        );
    }

    private function getSearchableFilterModel(): Model|null
    {
        $filterModel = $this->filterModel;

        if (!$filterModel instanceof Model) {
            return null;
        }

        if ($filterModel instanceof UserSearchInterface || method_exists($filterModel, 'search')) {
            return $filterModel;
        }

        return null;
    }

    /**
     * @param User $user Current web user component.
     *
     * @throws InvalidConfigException if the configured filter model cannot be created as a model.
     */
    private function initFilterModel(User $user): void
    {
        $filterModel = $this->filterModel;

        if (is_string($filterModel) && class_exists($filterModel)) {
            $model = Yii::createObject($filterModel);

            if (!$model instanceof Model) {
                throw new InvalidConfigException('User filter model must extend ' . Model::class . '.');
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
     * @param array<int|string, Item> $items RBAC items indexed by item name.
     *
     * @return array<int, array{
     *   name: string,
     *   description: string,
     *   ruleName: string|null,
     *   data: string,
     *   createdAt: int,
     *   updatedAt: int
     * }>
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
     * @param array<int|string, mixed> $data Raw identity data.
     *
     * @return array<string, mixed>
     */
    private static function normalizeStringKeyArray(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<int|string, mixed> $params Request query parameters.
     *
     * @throws InvalidConfigException if the filter model returns an invalid data provider.
     */
    private function searchUsers(Model $filterModel, array $params): DataProviderInterface
    {
        if ($filterModel instanceof UserSearchInterface) {
            $dataProvider = $filterModel->search($params);
        } elseif (is_callable([$filterModel, 'search'])) {
            $dataProvider = $filterModel->search($params);
        } else {
            throw new InvalidConfigException(
                'User filter model must provide a search method.',
            );
        }

        if (!$dataProvider instanceof DataProviderInterface) {
            throw new InvalidConfigException(
                'User filter model search method must return a data provider.',
            );
        }

        return $dataProvider;
    }
}
