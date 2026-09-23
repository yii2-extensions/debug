<?php

declare(strict_types=1);

namespace yii\debug\service;

use Closure;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;
use Yii;
use yii\base\{Application, BaseObject, InvalidConfigException};
use yii\debug\{ComponentResolver, Module};
use yii\debug\exception\Message;
use yii\helpers\ArrayHelper;

use function array_key_first;
use function class_exists;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use function is_subclass_of;
use function method_exists;

/**
 * Hands every collector named in {@see Module::$dispatchers} to the application component the application pairs it
 * with, so the component emits its PSR-14 events into the collector.
 *
 * The way the component takes the collector is detected, never declared: a public `eventDispatcher` property (or a Yii
 * getter/setter pair) wins, then a constructor parameter named `eventDispatcher`. A definition is amended without
 * instantiating the component; an instantiated component only accepts the property form. A dispatcher the component
 * already configures is kept, so a populated application dispatcher is never replaced by the collector.
 */
class DispatcherAttacher
{
    /**
     * @param Module $module Debug module owning the dispatcher map and the collector coordinator, read at call time.
     */
    public function __construct(protected readonly Module $module) {}

    /**
     * Attaches every configured collector to its application component.
     *
     * Runs once every bootstrap class had its turn, so a component a bootstrap class registers is seen. A collector
     * disabled through `enabled => false` is skipped; {@see CollectorRegistrar} already rejected an entry naming no
     * configured collector.
     *
     * @param Application $app Application owning the components.
     *
     * @throws InvalidConfigException when a collector is not a PSR-14 dispatcher, or when its component is unknown,
     * instantiated without a writable `eventDispatcher` property, or unable to take the collector.
     */
    public function attach(Application $app): void
    {
        $coordinator = $this->module->getCollectorCoordinator();

        foreach ($this->module->dispatchers as $collectorId => $componentId) {
            $collector = $coordinator->collector($collectorId);

            if ($collector === null) {
                continue;
            }

            if (!$collector instanceof EventDispatcherInterface) {
                throw new InvalidConfigException(
                    Message::DISPATCHER_COLLECTOR_NOT_DISPATCHER->getMessage(
                        $collectorId,
                        EventDispatcherInterface::class,
                    ),
                );
            }

            $definition = $app->has($componentId, true)
                ? $app->get($componentId)
                : ($app->getComponents()[$componentId] ?? null);

            if ($definition === null) {
                throw new InvalidConfigException(
                    Message::DISPATCHER_COMPONENT_UNKNOWN->getMessage($collectorId, $componentId),
                );
            }

            if (is_string($definition) || is_array($definition)) {
                $app->set($componentId, $this->amendDefinition($componentId, $definition, $collector));

                continue;
            }

            if (is_object($definition) === false || $definition instanceof Closure) {
                throw new InvalidConfigException(
                    Message::DISPATCHER_TARGET_UNSUPPORTED->getMessage($componentId),
                );
            }

            $this->attachToInstance($componentId, $definition, $collector);
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
        $constructor = (new ReflectionClass($class))->getConstructor();

        foreach ($constructor?->getParameters() ?? [] as $position => $parameter) {
            if ($parameter->getName() === 'eventDispatcher') {
                return $arguments === [] || is_int(array_key_first($arguments)) === false
                    ? 'eventDispatcher'
                    : $position;
            }
        }

        return null;
    }

    /**
     * Returns whether `$class` takes its dispatcher through a writable `eventDispatcher` property.
     *
     * @param class-string $class Component class to inspect.
     *
     * @return bool `true` for a public, non-static, non-readonly property or a Yii getter/setter pair; `false`
     * otherwise.
     */
    private static function acceptsProperty(string $class): bool
    {
        $reflection = new ReflectionClass($class);

        if ($reflection->hasProperty('eventDispatcher')) {
            $property = $reflection->getProperty('eventDispatcher');

            return $property->isPublic() && $property->isStatic() === false && $property->isReadOnly() === false;
        }

        return is_subclass_of($class, BaseObject::class)
            && method_exists($class, 'getEventDispatcher')
            && method_exists($class, 'setEventDispatcher');
    }

    /**
     * Returns the component definition carrying the collector, without instantiating the component.
     *
     * @param string $componentId Application component ID, named in a failure.
     * @param array<array-key, mixed>|string $definition Class name or configuration array the application declares.
     * @param EventDispatcherInterface $collector Collector handed to the component.
     *
     * @throws InvalidConfigException when the definition names no loadable class, or a class taking no dispatcher.
     *
     * @return array<array-key, mixed> Definition with the collector as its dispatcher, unless it configures one.
     */
    private function amendDefinition(
        string $componentId,
        array|string $definition,
        EventDispatcherInterface $collector,
    ): array {
        [$class] = ComponentResolver::classAndProperties($definition);

        if ($class === null || class_exists($class) === false) {
            throw new InvalidConfigException(
                Message::DISPATCHER_TARGET_UNSUPPORTED->getMessage($componentId),
            );
        }

        $definition = is_string($definition) ? ['class' => $definition] : $definition;

        if (self::acceptsProperty($class)) {
            $definition['eventDispatcher'] ??= $collector;

            return $definition;
        }

        $arguments = $definition['__construct()'] ?? [];

        $arguments = is_array($arguments) ? $arguments : [];

        $key = $this->dispatcherArgumentKey($class, $arguments);

        if ($key === null) {
            throw new InvalidConfigException(
                Message::DISPATCHER_TARGET_UNSUPPORTED->getMessage($componentId),
            );
        }

        $arguments[$key] ??= $collector;

        $definition['__construct()'] = $arguments;

        return $definition;
    }

    /**
     * Sets the collector on an instantiated component whose `eventDispatcher` property is still `null`.
     *
     * @param string $componentId Application component ID, named in a failure.
     * @param object $component Instantiated component.
     * @param EventDispatcherInterface $collector Collector handed to the component.
     *
     * @throws InvalidConfigException when the component exposes no writable `eventDispatcher` property.
     */
    private function attachToInstance(string $componentId, object $component, EventDispatcherInterface $collector): void
    {
        if (self::acceptsProperty($component::class) === false) {
            throw new InvalidConfigException(
                Message::DISPATCHER_COMPONENT_INSTANTIATED->getMessage($componentId),
            );
        }

        if (ArrayHelper::getValue($component, 'eventDispatcher') === null) {
            Yii::configure($component, ['eventDispatcher' => $collector]);
        }
    }
}
