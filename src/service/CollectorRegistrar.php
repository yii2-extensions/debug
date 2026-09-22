<?php

declare(strict_types=1);

namespace yii\debug\service;

use InvalidArgumentException;
use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\CollectorInterface;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\collectors\Collector;
use yii\debug\{ComponentResolver, Module};
use yii\debug\exception\Message;

use function array_key_exists;
use function is_array;
use function is_bool;
use function is_string;

/**
 * Resolves the configured debug collectors and validates their stable IDs before request capture.
 *
 * Built-in extension collectors are omitted when their provider package is unavailable. Explicit application
 * configuration remains authoritative and may still register a custom collector under the same ID.
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
     * optional package is not an error.
     *
     * Each resolved collector is instrumented right away through {@see Collector::instrument()}: the panels are built
     * afterwards and a panel constructor may already hit the framework, so instrumentation installed only at
     * {@see \yii\base\Application::EVENT_BEFORE_REQUEST} would miss the debugger's own bootstrap work.
     *
     * @param array<string, array<string, mixed>|string> $core Built-in collector definitions indexed by collector ID.
     * @param array<array-key, array<string, mixed>|CollectorInterface|string> $configured Collector definitions
     * declared by the application.
     *
     * @throws InvalidConfigException when a collector configuration, a collector ID, or the resolved set is invalid.
     *
     * @return CollectorCoordinator Coordinator holding the registered collectors in resolution order.
     */
    public function register(array $core, array $configured): CollectorCoordinator
    {
        $collectors = [];

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
                    continue;
                }
            }

            $collector = $this->buildCollector($config);

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
}
