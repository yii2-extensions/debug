<?php

declare(strict_types=1);

namespace yii\debug\service;

use Yii;
use yii\base\InvalidConfigException;
use yii\debug\{ComponentResolver, LogTarget, Module};
use yii\debug\exception\Message;

/**
 * Resolves the configured log target the module attaches to the application logger during the bootstrap.
 */
class LogTargetFactory
{
    /**
     * @param Module $module Debug module read for the log-target configuration at call time.
     */
    public function __construct(protected readonly Module $module) {}

    /**
     * Resolves {@see Module::$logTarget} into a {@see LogTarget} instance, accepting a class-name string, a
     * configuration array with a `class` key, or an already-instantiated target.
     *
     * The module itself is handed to the target as the first constructor argument, so a custom target reaches the
     * panels and the storage it captures into.
     *
     * @throws InvalidConfigException when the configured class is missing or does not produce a {@see LogTarget}.
     *
     * @return LogTarget Target built from the configured class name, array, or instance.
     */
    public function create(): LogTarget
    {
        if ($this->module->logTarget instanceof LogTarget) {
            return $this->module->logTarget;
        }

        [$class, $properties] = ComponentResolver::classAndProperties(
            $this->module->logTarget,
            LogTarget::class,
        );

        if ($class === null) {
            throw new InvalidConfigException(
                Message::LOG_TARGET_CLASS_INVALID->getMessage(),
            );
        }

        $target = Yii::$container->get($class, [$this->module], $properties);

        if (!$target instanceof LogTarget) {
            throw new InvalidConfigException(
                Message::LOG_TARGET_INSTANCE_INVALID->getMessage(),
            );
        }

        return $target;
    }
}
