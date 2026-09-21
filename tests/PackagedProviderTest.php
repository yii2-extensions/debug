<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Inertia\Debug\{InertiaCollector, InertiaPanel};
use PHPForge\Vite\Debug\{ViteCollector, VitePanel};
use PHPUnit\Framework\Attributes\Group;
use yii\debug\{PackagedProvider, ProviderAttachment};
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see PackagedProvider} describing an optional provider package and its collector definition.
 */
#[Group('module')]
final class PackagedProviderTest extends TestCase
{
    /**
     * Collector class of a package no application can install.
     */
    private const string GHOST_COLLECTOR = 'Acme\Debug\GhostCollector';

    public function testCollectorDefinitionBuildsConstructorArgumentsFromTheCapturePolicy(): void
    {
        $provider = new PackagedProvider(
            'inertia',
            InertiaCollector::class,
            InertiaPanel::class,
            'inertia',
            'yii\inertia\Manager',
            ProviderAttachment::Property,
            static fn(CapturePolicy $capturePolicy): array => [
                $capturePolicy->redact(...),
                $capturePolicy->redactUrl(...),
            ],
        );

        $definition = $provider->collectorDefinition(new CapturePolicy());

        self::assertIsArray(
            $definition,
            'Arguments must produce a configuration array.',
        );
        self::assertSame(
            InertiaCollector::class,
            $definition['class'] ?? null,
            'Entry must name the collector.',
        );

        $arguments = $definition['__construct()'] ?? null;

        self::assertIsArray(
            $arguments,
            'Entry must carry the constructor arguments.',
        );
        self::assertCount(
            2,
            $arguments,
            'Closure result must pass through verbatim.',
        );
        self::assertIsCallable(
            $arguments[0] ?? null,
            'Policy callables must reach the collector.',
        );
    }

    public function testCollectorDefinitionReturnsTheCollectorClassWithoutConstructorArguments(): void
    {
        $provider = $this->vite();

        self::assertSame(
            ViteCollector::class,
            $provider->collectorDefinition(new CapturePolicy()),
            'Class alone must be the whole definition.',
        );
    }

    public function testInstalledReportsAnAbsentPackage(): void
    {
        $provider = new PackagedProvider(
            'ghost',
            self::GHOST_COLLECTOR,
            'Acme\Debug\GhostPanel',
            'ghost',
            'Acme\Ghost',
            ProviderAttachment::Constructor,
        );

        self::assertFalse(
            $provider->installed(),
            'An unloadable collector means an absent package.',
        );
    }

    public function testInstalledReportsAnInstalledPackage(): void
    {
        self::assertTrue(
            $this->vite()->installed(),
            'A loadable collector means an installed package.',
        );
    }

    public function testProviderKeepsTheDeclaredMetadata(): void
    {
        $provider = $this->vite();

        self::assertSame(
            'vite',
            $provider->id,
            'ID must be the stable provider ID.',
        );
        self::assertSame(
            ViteCollector::class,
            $provider->collector,
            'Collector must be the packaged one.',
        );
        self::assertSame(
            VitePanel::class,
            $provider->panel,
            'Panel must be the packaged one.',
        );
        self::assertSame(
            'vite',
            $provider->component,
            'Component must be the host application component ID.',
        );
        self::assertSame(
            'PHPForge\Vite\Vite',
            $provider->componentClass,
            'Component class must be the host service.',
        );
        self::assertSame(
            ProviderAttachment::Constructor,
            $provider->attachment,
            'A service taking the dispatcher as an argument attaches through the constructor.',
        );
        self::assertNull(
            $provider->constructorArguments,
            'A collector taking no arguments declares none.',
        );
    }

    /**
     * Returns a provider describing the packaged Vite integration.
     */
    private function vite(): PackagedProvider
    {
        return new PackagedProvider(
            'vite',
            ViteCollector::class,
            VitePanel::class,
            'vite',
            'PHPForge\Vite\Vite',
            ProviderAttachment::Constructor,
        );
    }
}
