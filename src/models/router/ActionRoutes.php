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

use function is_array;

/**
 * Discovers every controller action reachable from the running application and pairs it with its matching URL rule.
 *
 * Walks the module tree starting from {@see Yii::$app}, reflects each controller to enumerate its action methods, and
 * resolves the first creation-time URL rule that matches the resulting route.
 */
class ActionRoutes extends Model
{
    /**
     * @var array<string, array{route: string, rule: string|null, count: int}> Discovered actions keyed by display name,
     * each carrying its route, matched URL rule (or `null`), and the number of rules scanned.
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
     * Returns the action method names declared on the given controller, plus a sentinel when it overrides `actions()`.
     *
     * @param ReflectionClass<Controller> $controller Reflection over the target controller class.
     *
     * @return list<non-empty-string> Action method names (`actionFoo`), or `__ACTIONS__` for controllers that declare
     * external actions.
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
     * Returns every web/REST controller reachable from the application, grouped by controller ID.
     *
     * @throws ReflectionException When a controller class fails to reflect.
     *
     * @return array<string, array{class: class-string<Controller>, actions: list<non-empty-string>}> Controllers keyed
     * by ID, each carrying its FQCN and the action method names it exposes.
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
     * Returns the first URL rule name that successfully creates a URL for the given route.
     *
     * @param string $route Route to test against every configured URL rule.
     *
     * @return array{0: string|null, 1: int} Matching rule name (or `null` when no rule matches) and the number of rules
     * actually scanned before deciding.
     */
    protected function getMatchedCreationRule(string $route): array
    {
        $count = 0;

        $urlManager = Yii::$app->urlManager;

        if ($urlManager->enablePrettyUrl) {
            /** @var UrlRuleInterface $rule */
            foreach ($urlManager->rules as $rule) {
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
     * Returns every controller available in the given module (and recursively its child modules).
     *
     * Scans the module's controller path for `*Controller.php` files and applies `controllerMap` overrides on top.
     *
     * @param Module $module Module to scan, including its child modules.
     *
     * @throws ReflectionException When a controller class fails to reflect.
     *
     * @return array<string, class-string<Controller>> Controller class names indexed by route prefix.
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

                /** @var SplFileInfo $fileInfo */
                foreach ($iterator as $fileInfo) {
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
                            $controllerId = "{$dir}/{$controllerId}";
                        }

                        $controllers["{$prefix}{$controllerId}"] = $controllerClass;
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
     * Returns whether the given class is a concrete `yii\web\Controller` or `yii\rest\Controller` subclass.
     *
     * @param string $controllerClass Fully qualified class name to validate.
     *
     * @throws ReflectionException When reflection over the class fails.
     *
     * @return bool `true` when the class is loadable, non-abstract, and extends a web/REST controller, `false`
     * otherwise.
     *
     * @phpstan-assert-if-true class-string<Controller> $controllerClass
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
     * Returns the rule name when the rule is a {@see UrlRule} with a successful creation status, recursing into the
     * subrules of any {@see GroupUrlRule}.
     *
     * @param UrlRuleInterface $rule Rule to inspect.
     *
     * @return string|null Matching rule name, or `null` when nothing inside the rule matches.
     */
    private function getRuleName(UrlRuleInterface $rule): string|null
    {
        $name = null;

        if ($rule instanceof UrlRule && $rule->getCreateUrlStatus() === UrlRule::CREATE_STATUS_SUCCESS) {
            $name = is_string($rule->name) ? $rule->name : null;
        } elseif ($rule instanceof GroupUrlRule) {
            /** @var UrlRuleInterface $subrule */
            foreach ($rule->rules as $subrule) {
                $name = $this->getRuleName($subrule);

                if ($name !== null) {
                    break;
                }
            }
        }

        return $name;
    }
}
