<?php

declare(strict_types=1);

namespace yii\debug\service;

use PHPForge\Debug\Collector\CollectorCoordinator;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;
use Yii;
use yii\base\{Application, Component};
use yii\debug\{ComponentResolver, PackagedProvider, ProviderAttachment, ProviderCatalog};
use yii\helpers\ArrayHelper;

use function array_key_first;
use function is_array;
use function is_int;
use function is_object;
use function is_string;

/**
 * Hands the collector of every installed provider to the application component it observes, so the provider emits
 * its results into it.
 *
 * A {@see ProviderAttachment::Property} component takes the collector on the `eventDispatcher` property, in a
 * definition or on a live instance alike; a {@see ProviderAttachment::Constructor} component takes it as a
 * constructor argument, so only a definition is amended and an already-instantiated component is left alone.
 */
class ProviderCollectorAttacher
{
    /**
     * @param ProviderCatalog $catalog Providers the debugger wires on its own.
     * @param CollectorCoordinator $coordinator Coordinator holding the registered collectors.
     */
    public function __construct(
        protected readonly ProviderCatalog $catalog,
        protected readonly CollectorCoordinator $coordinator,
    ) {}

    /**
     * Attaches the collector of every installed provider to its application component.
     *
     * Runs once every bootstrap class had its turn, so a component a provider bootstrap registers is seen. A
     * definition is amended without instantiating the component. A component is left alone when
     * {@see PackagedProvider::attachesTo()} rejects its class, when it already carries a dispatcher, when its
     * constructor declares no `eventDispatcher` parameter, or when the collector is not registered.
     *
     * @param Application $app Application owning the provider components.
     */
    public function attach(Application $app): void
    {
        foreach ($this->catalog->installed() as $provider) {
            $collector = $this->coordinator->collector($provider->id);

            if (!$collector instanceof EventDispatcherInterface) {
                continue;
            }

            $id = $provider->component;
            $definition = $app->has($id, true) ? $app->get($id) : ($app->getComponents()[$id] ?? null);

            /** @var class-string $componentClass */
            $componentClass = $provider->componentClass;

            if (is_object($definition)) {
                if (
                    $provider->attachment === ProviderAttachment::Property
                    && $definition instanceof Component
                    && $definition instanceof $componentClass
                    && $definition->canGetProperty('eventDispatcher')
                    && $definition->canSetProperty('eventDispatcher')
                    && ArrayHelper::getValue($definition, 'eventDispatcher') === null
                ) {
                    Yii::configure($definition, ['eventDispatcher' => $collector]);
                }

                continue;
            }

            if (is_string($definition) === false && is_array($definition) === false) {
                continue;
            }

            [$class] = ComponentResolver::classAndProperties($definition);

            if ($provider->attachesTo($class) === false) {
                continue;
            }

            $definition = is_string($definition) ? ['class' => $definition] : $definition;

            if ($provider->attachment === ProviderAttachment::Property) {
                $definition['eventDispatcher'] ??= $collector;
            } else {
                $arguments = $definition['__construct()'] ?? [];
                $arguments = is_array($arguments) ? $arguments : [];

                $key = $this->dispatcherArgumentKey($class, $arguments);

                if ($key === null) {
                    continue;
                }

                $arguments[$key] ??= $collector;

                $definition['__construct()'] = $arguments;
            }

            $app->set($id, $definition);
        }
    }

    /**
     * Returns the `__construct()` key a component definition takes its dispatcher under.
     *
     * A definition indexing its arguments by position gets the dispatcher at the position its constructor declares,
     * because Yii rejects a definition mixing named and positional arguments; every other definition gets it by name.
     *
     * Override point: a subclass returning its own key attaches the collector to a component whose constructor names
     * the dispatcher parameter differently.
     *
     * @param class-string $class Component class whose constructor is inspected.
     * @param array<array-key, mixed> $arguments Arguments the definition already declares.
     *
     * @return int|string|null Key to write the dispatcher under, or `null` when the constructor declares no
     * `eventDispatcher` parameter.
     */
    protected function dispatcherArgumentKey(string $class, array $arguments): int|string|null
    {
        if ($arguments === [] || is_int(array_key_first($arguments)) === false) {
            return 'eventDispatcher';
        }

        $constructor = (new ReflectionClass($class))->getConstructor();

        foreach ($constructor?->getParameters() ?? [] as $position => $parameter) {
            if ($parameter->getName() === 'eventDispatcher') {
                return $position;
            }
        }

        return null;
    }
}
