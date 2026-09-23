<?php

declare(strict_types=1);

namespace yii\debug\service;

use Closure;
use InvalidArgumentException;
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\CollectorInterface;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\collectors\Collector;
use yii\debug\{ComponentResolver, Module};
use yii\debug\exception\Message;

use function array_flip;
use function array_key_exists;
use function get_debug_type;
use function is_array;
use function is_bool;
use function is_string;

/**
 * Resolves the configured debug collectors and validates their stable IDs before request capture.
 *
 * Built-in collectors are omitted when their optional package is unavailable. Explicit application configuration
 * remains authoritative and may still register a custom collector under the same ID.
 */
class CollectorRegistrar
{
    /**
     * @param Module $module Debug module owning the collectors and read for configuration at call time.
     */
    public function __construct(protected readonly Module $module) {}

    /**
     * Instantiates every configured collector, instruments it, and returns the coordinator driving the capture.
     *
     * An array entry declaring `enabled` as `false` is skipped before its class is resolved, so an uninstalled
     * optional package is not an error. A `Closure` entry receives the module {@see CapturePolicy}, built once per
     * call, so a provider collector redacts with the rules the Request panel applies.
     *
     * Every {@see Module::$dispatchers} key must name a registered or a disabled collector, so a mistyped entry fails
     * here instead of leaving its panel silently empty.
     *
     * Each resolved collector is instrumented right away through {@see Collector::instrument()}: the panels are built
     * afterwards and a panel constructor may already hit the framework, so instrumentation installed only at
     * {@see \yii\base\Application::EVENT_BEFORE_REQUEST} would miss the debugger's own bootstrap work.
     *
     * @param array<string, array<string, mixed>|string> $core Built-in collector definitions indexed by collector ID.
     * @param array<array-key, array<string, mixed>|(Closure(CapturePolicy): mixed)|CollectorInterface|string> $configured
     * Collector definitions declared by the application; a `Closure` result is validated at call time.
     *
     * @throws InvalidConfigException when a collector configuration, a collector ID, a dispatcher entry, or the
     * resolved set is invalid.
     *
     * @return CollectorCoordinator Coordinator holding the registered collectors in resolution order.
     */
    public function register(array $core, array $configured): CollectorCoordinator
    {
        $collectors = [];
        $disabled = [];
        $policy = null;

        foreach (CoreDefinitions::merge($core, $configured) as $id => $config) {
            if (is_array($config) && array_key_exists('enabled', $config)) {
                $enabled = $config['enabled'];

                unset($config['enabled']);

                if (is_bool($enabled) === false) {
                    throw new InvalidConfigException(
                        Message::COLLECTOR_ENABLED_INVALID->getMessage((string) $id),
                    );
                }

                if ($enabled === false) {
                    $disabled[] = $id;

                    continue;
                }
            }

            if ($config instanceof Closure) {
                $policy ??= $this->module->createCapturePolicy();
                $collector = self::callFactory($config, $policy);
            } else {
                $collector = $this->buildCollector($config);
            }

            if (is_string($id) && $id !== $collector->id()) {
                throw new InvalidConfigException(
                    Message::PROVIDER_ID_MISMATCH->getMessage('collector'),
                );
            }

            if ($collector instanceof Collector) {
                $collector->module = $this->module;

                $collector->instrument();
            }

            $collectors[] = $collector;
        }

        $this->assertDispatchersKnown($collectors, $disabled);

        try {
            return new CollectorCoordinator($collectors);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidConfigException(
                $exception->getMessage(),
                0,
                $exception,
            );
        }
    }

    /**
     * Rejects a {@see Module::$dispatchers} key that names neither a registered nor a disabled collector.
     *
     * @param list<CollectorInterface> $collectors Registered collectors.
     * @param list<array-key> $disabled IDs of the collectors disabled through `enabled => false`.
     *
     * @throws InvalidConfigException when a dispatcher entry names no configured collector.
     */
    private function assertDispatchersKnown(array $collectors, array $disabled): void
    {
        $ids = $disabled;

        foreach ($collectors as $collector) {
            $ids[] = $collector->id();
        }

        $known = array_flip($ids);

        foreach ($this->module->dispatchers as $collectorId => $_componentId) {
            if (isset($known[$collectorId]) === false) {
                throw new InvalidConfigException(
                    Message::DISPATCHER_COLLECTOR_UNKNOWN->getMessage($collectorId),
                );
            }
        }
    }

    /**
     * Resolves a collector instance, class name, or Yii configuration array.
     *
     * @param array<string, mixed>|CollectorInterface|string $config Collector configuration.
     *
     * @throws InvalidConfigException when the configuration does not resolve to a collector.
     *
     * @return CollectorInterface Resolved collector.
     */
    private function buildCollector(CollectorInterface|array|string $config): CollectorInterface
    {
        if ($config instanceof CollectorInterface) {
            return $config;
        }

        [$class, $properties] = ComponentResolver::classAndProperties($config);

        if ($class === null) {
            throw new InvalidConfigException(
                Message::COLLECTOR_CLASS_INVALID->getMessage(),
            );
        }

        $collector = Yii::$container->get($class, [], $properties);

        if (!$collector instanceof CollectorInterface) {
            throw new InvalidConfigException(
                Message::COLLECTOR_INTERFACE_INVALID->getMessage(CollectorInterface::class, $class),
            );
        }

        return $collector;
    }

    /**
     * Builds a collector from a `Closure` entry, handing it the module capture policy.
     *
     * @param Closure(CapturePolicy): mixed $factory Entry declared by the application, expected to return a collector.
     * @param CapturePolicy $policy Module capture policy the collector redacts with.
     *
     * @throws InvalidConfigException when the entry returns anything but a collector.
     *
     * @return CollectorInterface Collector the entry built.
     */
    private static function callFactory(Closure $factory, CapturePolicy $policy): CollectorInterface
    {
        $collector = $factory($policy);

        if (!$collector instanceof CollectorInterface) {
            throw new InvalidConfigException(
                Message::COLLECTOR_INTERFACE_INVALID->getMessage(CollectorInterface::class, get_debug_type($collector)),
            );
        }

        return $collector;
    }
}
