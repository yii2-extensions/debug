<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Exception;
use Yii;
use yii\base\Controller;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\data\ArrayDataProvider;
use yii\data\DataProviderInterface;
use yii\db\ActiveRecord;
use yii\debug\controllers\UserController;
use yii\debug\models\search\UserSearchInterface;
use yii\debug\models\UserSwitch;
use yii\debug\Panel;
use yii\filters\AccessControl;
use yii\filters\AccessRule;
use yii\helpers\ArrayHelper;
use yii\helpers\VarDumper;
use yii\rbac\ManagerInterface;
use yii\web\IdentityInterface;
use yii\web\User;

use function class_exists;
use function class_implements;
use function get_object_vars;
use function in_array;
use function is_string;
use function is_subclass_of;

/**
 * Debugger panel that collects and displays user data.
 */
class UserPanel extends Panel
{
    /**
     * @var array the rule which defines who allowed to switch user identity.
     * Access Control Filter single rule. Ignore: actions, controllers, verbs.
     * Settable: allow, roles, ips, matchCallback, denyCallback.
     * By default, deny for everyone. Recommendation: can allow for administrator or developer (if implement)
     * role: ['allow' => true, 'roles' => ['admin']]
     */
    public array $ruleUserSwitch = [
        'allow' => false,
    ];
    /**
     * @var UserSwitch object of switching users.
     */
    public UserSwitch $userSwitch;
    /**
     * @var Model|UserSearchInterface|null Implements of a User model with search method.
     */
    public $filterModel;
    /**
     * @var array allowed columns for GridView.
     *
     * @see http://www.yiiframework.com/doc-2.0/yii-grid-gridview.html#$columns-detail
     */
    public array $filterColumns = [];
    /**
     * @var string|User ID of the user component or a user object.
     */
    public string|User $userComponent = 'user';
    /**
     * @var string Display Name of the debug panel.
     */
    public string $displayName = 'User';

    /**
     * {@inheritdoc}
     *
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        if (!$this->isEnabled() || $this->getUser()->isGuest) {
            return;
        }

        $this->userSwitch = new UserSwitch(['userComponent' => $this->userComponent]);
        $this->addAccessRules();

        if (
            is_string($this->filterModel) &&
            class_exists($this->filterModel) &&
            in_array(UserSearchInterface::class, class_implements($this->filterModel), true)
        ) {
            $this->filterModel = new $this->filterModel();
        } elseif ($this->getUser() && $this->getUser()->identityClass) {
            if (is_subclass_of($this->getUser()->identityClass, ActiveRecord::class)) {
                $this->filterModel = new \yii\debug\models\search\User();
            }
        }
    }

    /**
     * @throws InvalidConfigException
     */
    public function getUser(): User|string|null
    {
        /* @var User $user */
        return is_string($this->userComponent) ? Yii::$app->get($this->userComponent, false) : $this->userComponent;
    }

    /**
     * Add ACF rule. AccessControl attach to debug module.
     * Access rule for main user.
     */
    private function addAccessRules(): void
    {
        $this->ruleUserSwitch['controllers'] = [$this->module->getUniqueId() . '/user'];

        $this->module->attachBehavior(
            'access_debug',
            [
                'class' => AccessControl::class,
                'only' => [$this->module->getUniqueId() . '/user', $this->module->getUniqueId() . '/default'],
                'user' => $this->userSwitch->getMainUser(),
                'rules' => [
                    $this->ruleUserSwitch,
                ],
            ]
        );
    }

    /**
     * Get model for GridView -> FilterModel
     */
    public function getUsersFilterModel(): UserSearchInterface|Model
    {
        return $this->filterModel;
    }

    /**
     * Get model for GridView -> DataProvider.
     */
    public function getUserDataProvider(): DataProviderInterface
    {
        return $this->getUsersFilterModel()->search(Yii::$app->request->queryParams);
    }

    /**
     * Check is available search of users.
     */
    public function canSearchUsers(): bool
    {
        return isset($this->filterModel) &&
            $this->filterModel instanceof Model &&
            $this->filterModel->hasMethod('search')
        ;
    }

    /**
     * Check can the main user switch identity.
     *
     * @throws InvalidConfigException
     */
    public function canSwitchUser(): bool
    {
        if ($this->getUser()->isGuest) {
            return false;
        }

        $allowSwitchUser = false;

        $rule = new AccessRule($this->ruleUserSwitch);

        /** @var Controller $userController */
        $userController = null;
        $controller = $this->module->createController('user');

        if (isset($controller[0]) && $controller[0] instanceof UserController) {
            $userController = $controller[0];
        }

        //check by rule
        if ($userController) {
            $action = $userController->createAction('set-identity');
            $user = $this->userSwitch->getMainUser();
            $request = Yii::$app->request;

            $allowSwitchUser = $rule->allows($action, $user, $request) ?: false;
        }

        return $allowSwitchUser;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->displayName;
    }

    /**
     * {@inheritdoc}
     */
    public function getSummary(): string
    {
        return Yii::$app->view->render('panels/user/summary', ['panel' => $this]);
    }

    /**
     * {@inheritdoc}
     */
    public function getDetail(): string
    {
        return Yii::$app->view->render('panels/user/detail', ['panel' => $this]);
    }

    /**
     * {@inheritdoc}
     */
    public function save(): mixed
    {
        $identity = Yii::$app->{$this->userComponent}->identity;

        if (!isset($identity)) {
            return null;
        }

        $rolesProvider = null;
        $permissionsProvider = null;

        try {
            $authManager = Yii::$app->getAuthManager();

            if ($authManager instanceof ManagerInterface) {
                $roles = ArrayHelper::toArray($authManager->getRolesByUser($this->getUser()->id));
                foreach ($roles as &$role) {
                    $role['data'] = $this->dataToString($role['data']);
                }
                unset($role);
                $rolesProvider = new ArrayDataProvider([
                    'allModels' => $roles,
                ]);

                $permissions = ArrayHelper::toArray($authManager->getPermissionsByUser($this->getUser()->id));
                foreach ($permissions as &$permission) {
                    $permission['data'] = $this->dataToString($permission['data']);
                }
                unset($permission);

                $permissionsProvider = new ArrayDataProvider([
                    'allModels' => $permissions,
                ]);
            }
        } catch (Exception $e) {
            // ignore auth manager misconfiguration
        }

        $identityData = $this->identityData($identity);

        foreach ($identityData as $key => $value) {
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
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        try {
            $this->getUser();
        } catch (InvalidConfigException $exception) {
            return false;
        }
        return true;
    }

    /**
     * Converts mixed data to string.
     */
    protected function dataToString(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        return VarDumper::export($data);
    }

    /**
     * Returns the array that should be set on [[\yii\widgets\DetailView::model]].
     */
    protected function identityData(IdentityInterface $identity): array
    {
        if ($identity instanceof Model) {
            return $identity->getAttributes();
        }

        return get_object_vars($identity);
    }
}
