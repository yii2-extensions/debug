<?php

declare(strict_types=1);

namespace yii\debug\service;

use yii\base\{Action, InvalidConfigException};
use yii\debug\{ComponentResolver, Module, Panel};

use function str_contains;
use function trim;

/**
 * Merges the standalone actions the debugger exposes and resolves a route against the resulting map.
 */
class StandaloneActionResolver
{
    /**
     * @param Module $module Debug module read for the action map and the default route at call time.
     */
    public function __construct(protected readonly Module $module) {}

    /**
     * Merges the built-in and panel-declared standalone actions with the configured ones.
     *
     * Precedence, lowest to highest: the built-in actions, the actions registered panels declare through
     * {@see Panel::$actions}, and the entries the application configured directly.
     *
     * @param array<string, class-string> $core Built-in actions indexed by action ID.
     * @param array<string, Panel> $panels Registered panels contributing their own actions.
     * @param array<string, array<string, mixed>|class-string> $configured Actions configured by the application.
     *
     * @return array<string, array<string, mixed>|class-string> Merged action map indexed by action ID.
     */
    public function map(array $core, array $panels, array $configured): array
    {
        $panelActions = [];

        foreach ($panels as $panel) {
            foreach ($panel->actions as $id => $action) {
                $panelActions[$id] = $action;
            }
        }

        return [...$core, ...$panelActions, ...$configured];
    }

    /**
     * Resolves a route against the module action map and binds the action it names.
     *
     * An empty route falls back to {@see Module::$defaultRoute}; surrounding slashes are ignored, and a route naming
     * more than one segment is left to convention-based discovery.
     *
     * @param string $route Action route relative to the module.
     *
     * @throws InvalidConfigException when object creation fails for a resolvable entry.
     *
     * @return Action|null Action bound to the module, or `null` when the route names no mapped action.
     */
    public function resolve(string $route): Action|null
    {
        if ($route === '') {
            $route = $this->module->defaultRoute;
        }

        $id = trim($route, '/');

        if ($id !== '' && !str_contains($id, '/') && isset($this->module->actionMap[$id])) {
            $action = ComponentResolver::createMapped($this->module->actionMap[$id]);

            if ($action instanceof Action) {
                $action->id = $id;

                $action->setModule($this->module);

                return $action;
            }
        }

        return null;
    }
}
