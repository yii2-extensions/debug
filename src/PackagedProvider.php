<?php

declare(strict_types=1);

namespace yii\debug;

use Closure;
use PHPForge\Debug\Capture\CapturePolicy;

/**
 * Describes the collector, the panel, and the host component one optional provider package contributes.
 *
 * Every class name stays a plain `string`, because a package the application did not install names classes that never
 * resolve; {@see installed()} tells the two apart.
 */
final readonly class PackagedProvider
{
    /**
     * @param string $id Stable ID the collector and the panel both report.
     * @param string $collector Collector class the provider package ships.
     * @param string $panel Panel class the provider package ships.
     * @param string $component Application component ID the collector attaches to.
     * @param string $componentClass Class the application component must be for the attachment to apply.
     * @param ProviderAttachment $attachment Way the component takes the collector.
     * @param (Closure(CapturePolicy): list<mixed>)|null $constructorArguments Builds the collector constructor
     * arguments from the host capture policy, or `null` when the collector takes none.
     */
    public function __construct(
        public string $id,
        public string $collector,
        public string $panel,
        public string $component,
        public string $componentClass,
        public ProviderAttachment $attachment,
        public Closure|null $constructorArguments = null,
    ) {}

    /**
     * Returns the definition the module registers the collector under.
     *
     * @param CapturePolicy $capturePolicy Host redaction policy handed to a collector that takes one.
     *
     * @return array<string, mixed>|string Configuration array when the collector takes constructor arguments; the
     * collector class name otherwise.
     */
    public function collectorDefinition(CapturePolicy $capturePolicy): array|string
    {
        if ($this->constructorArguments === null) {
            return $this->collector;
        }

        return ['class' => $this->collector, '__construct()' => ($this->constructorArguments)($capturePolicy)];
    }

    /**
     * Returns whether the provider package is installed, so the debugger wires it.
     *
     * @return bool `true` when the collector class is loadable; `false` when the package is absent.
     */
    public function installed(): bool
    {
        return class_exists($this->collector);
    }
}
