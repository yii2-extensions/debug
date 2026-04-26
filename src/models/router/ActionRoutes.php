<?php

declare(strict_types=1);

namespace yii\debug\models\router;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use SplFileInfo;
use Yii;
use yii\base\{Application, Controller, Model, Module};
use yii\helpers\Inflector;
use yii\web\{GroupUrlRule, UrlRule, UrlRuleInterface};

use function count;
use function is_array;
use function is_string;

/**
 * Collects all available controller actions and their matching routes.
 */
class ActionRoutes extends Model
{
    /**
     * @var array<string, array{route: string, rule: string|null, count: int}> Scanned actions with matching routes
     */
    public array $routes = [];

    public function init(): void
    {
        parent::init();

        $appRoutes = $this->getAppRoutes();

        foreach ($appRoutes as $controller => $details) {
            $controllerClass = $details['class'];

            foreach ($details['actions'] as $actionName) {
                if ($actionName === '__ACTIONS__') {
                    $name = $controllerClass . '::actions()';
                    $route = $controller . '/[external-action]';
                    $rule = null;
                    $count = 0;
                } else {
                    $actionId = substr($actionName, 6);
                    $normalizedActionId = preg_replace('/\p{Lu}/u', '-\0', $actionId);

                    if ($normalizedActionId === null) {
                        continue;
                    }

                    $route = "$controller/" . mb_strtolower(trim($normalizedActionId, '-'), 'UTF-8');

                    [$rule, $count] = $this->getMatchedCreationRule($route);

                    $name = "{$controllerClass}::{$actionName}()";
                }

                $this->routes[$name] = [
                    'count' => $count,
                    'route' => $route,
                    'rule' => $rule,
                ];
            }
        }

        if ($this->routes !== []) {
            ksort($this->routes);
        }
    }

    /**
     * Returns all available actions of the specified controller.
     *
     * @param ReflectionClass<Controller> $controller Reflection of the controller.
     *
     * @return list<non-empty-string> All available action IDs with optional action class name (for external actions).
     */
    protected function getActions(ReflectionClass $controller): array
    {
        $actions = [];

        $methods = $controller->getMethods();

        foreach ($methods as $method) {
            $name = $method->getName();

            if ($name === 'actions') {
                $actions[] = '__ACTIONS__';
            } elseif ($method->isPublic() && !$method->isStatic() && strncmp($name, 'action', 6) === 0) {
                $actions[] = $name;
            }
        }

        return $actions;
    }

    /**
     * Returns all available application routes (non-console) grouped by the controller's name.
     *
     * @throws ReflectionException if any controller class does not exist or is not a valid controller.
     *
     * @return array<string, array{class: class-string<Controller>, actions: list<non-empty-string>}> Available
     * controllers and their actions, where the key is the controller ID and the value contains the controller class and
     * its actions.
     */
    protected function getAppRoutes(): array
    {
        $controllers = $this->getModuleControllers(Yii::$app);

        $appRoutes = [];

        foreach ($controllers as $controllerId => $controllerClass) {
            $class = new ReflectionClass($controllerClass);

            $actions = $this->getActions($class);

            if (count($actions) === 0) {
                continue;
            }

            $appRoutes[$controllerId] = [
                'actions' => $actions,
                'class' => $controllerClass,
            ];
        }

        return $appRoutes;
    }

    /**
     * Returns the first rule's name that matched given route (for creation) with number of scanned rules.
     *
     * @param string $route Route to be checked against URL rules.
     *
     * @return array{0: string|null, 1: int} Rule name (or null if not matched) and number of scanned rules.
     */
    protected function getMatchedCreationRule(string $route): array
    {
        $count = 0;

        $urlManager = Yii::$app->urlManager;

        if ($urlManager->enablePrettyUrl) {
            foreach ($urlManager->rules as $rule) {
                if (!$rule instanceof UrlRuleInterface) {
                    continue;
                }

                $count++;

                $url = $rule->createUrl($urlManager, $route, []);

                if ($url !== false) {
                    return [
                        $this->getRuleName($rule),
                        $count,
                    ];
                }
            }
        }

        return [null, $count];
    }

    /**
     * Returns available controllers of a specified module.
     *
     * @param Module $module Module instance.
     *
     * @throws ReflectionException if any controller class does not exist or is not a valid controller.
     *
     * @return array<string, class-string<Controller>> Available controller IDs and their class names.
     */
    protected function getModuleControllers(Module $module): array
    {
        $prefix = $module instanceof Application ? '' : $module->getUniqueId() . '/';

        $controllers = [];

        $modules = $module->getModules();

        foreach ($modules as $id => $child) {
            if (!is_string($id)) {
                continue;
            }

            if (($child = $module->getModule($id)) === null) {
                continue;
            }

            $moduleControllers = $this->getModuleControllers($child);

            foreach ($moduleControllers as $controllerId => $controllerClass) {
                $controllers[$controllerId] = $controllerClass;
            }
        }

        if (is_string($module->controllerNamespace)) {
            $controllerPath = $module->getControllerPath();

            if (is_dir($controllerPath)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($controllerPath, RecursiveDirectoryIterator::KEY_AS_PATHNAME),
                );

                foreach ($iterator as $fileInfo) {
                    if (!$fileInfo instanceof SplFileInfo) {
                        continue;
                    }

                    $file = $fileInfo->getPathname();

                    if (!str_ends_with($file, 'Controller.php')) {
                        continue;
                    }

                    $relativePath = str_replace($controllerPath, '', $file);
                    $class = strtr($relativePath, ['/' => '\\', '.php' => '']);

                    $controllerClass = $module->controllerNamespace . $class;

                    if ($this->validateControllerClass($controllerClass)) {
                        $dir = ltrim(pathinfo($relativePath, PATHINFO_DIRNAME), '\\/');

                        $controllerId = Inflector::camel2id(substr(basename($file), 0, -14), '-', true);

                        if ($dir !== '') {
                            $controllerId = $dir . '/' . $controllerId;
                        }

                        $controllers[$prefix . $controllerId] = $controllerClass;
                    }
                }
            }
        }

        // controllerMap takes precedence
        foreach ($module->controllerMap as $controllerId => $controllerConfig) {
            if (!is_string($controllerId)) {
                continue;
            }

            $controllerClass = null;

            if (is_array($controllerConfig)) {
                if (isset($controllerConfig['class']) && is_string($controllerConfig['class'])) {
                    $controllerClass = $controllerConfig['class'];
                } elseif (isset($controllerConfig['__class']) && is_string($controllerConfig['__class'])) {
                    $controllerClass = $controllerConfig['__class'];
                }
            } elseif (is_string($controllerConfig)) {
                $controllerClass = $controllerConfig;
            }

            if ($controllerClass !== null && $this->validateControllerClass($controllerClass)) {
                $controllers[$prefix . $controllerId] = $controllerClass;
            }
        }

        return $controllers;
    }

    /**
     * Validates if the given class is a valid web or REST controller class.
     *
     * @param string $controllerClass Fully qualified class name of the controller to validate.
     *
     * @throws ReflectionException if the controller class does not exist or is not a valid controller.
     *
     * @return bool `true` if the class exists and is a valid controller, `false` otherwise.
     *
     * @phpstan-assert-if-true class-string<Controller> $controllerClass if the class exists and is a valid controller.
     */
    protected function validateControllerClass(string $controllerClass): bool
    {
        if (
            !class_exists($controllerClass)
            || (
                !is_subclass_of($controllerClass, 'yii\web\Controller')
                && !is_subclass_of($controllerClass, 'yii\rest\Controller')
            )
        ) {
            return false;
        }

        $class = new ReflectionClass($controllerClass);

        return !$class->isAbstract();
    }

    /**
     * Returns the name of the rule if it is a `UrlRule` with successful creation status, or recursively checks subrules
     * if it's a `GroupUrlRule`.
     *
     * @param UrlRuleInterface $rule URL rule to check.
     *
     * @return string|null Name of the rule if it matches the criteria, or null if it doesn't match or if no matching
     * rule is found in the group.
     */
    private function getRuleName(UrlRuleInterface $rule): string|null
    {
        $name = null;

        if ($rule instanceof UrlRule && $rule->getCreateUrlStatus() === UrlRule::CREATE_STATUS_SUCCESS) {
            $name = is_string($rule->name) ? $rule->name : null;
        } elseif ($rule instanceof GroupUrlRule) {
            foreach ($rule->rules as $subrule) {
                if (!$subrule instanceof UrlRuleInterface) {
                    continue;
                }

                $name = $this->getRuleName($subrule);

                if ($name !== null) {
                    break;
                }
            }
        }

        return $name;
    }
}
