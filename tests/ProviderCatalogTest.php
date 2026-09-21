<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Inertia\Debug\{InertiaCollector, InertiaPanel};
use PHPForge\Vite\Debug\{ViteCollector, VitePanel};
use PHPUnit\Framework\Attributes\Group;
use yii\debug\{PackagedProvider, ProviderAttachment, ProviderCatalog};
use yii\debug\tests\support\TestCase;

use function array_map;

/**
 * Unit tests for {@see ProviderCatalog} listing the optional providers the debugger wires on its own.
 */
#[Group('module')]
final class ProviderCatalogTest extends TestCase
{
    public function testCollectorsAndPanelsSkipAProviderWhosePackageIsAbsent(): void
    {
        $catalog = new ProviderCatalog($this->ghost(), $this->vite());

        self::assertSame(
            ['vite' => ViteCollector::class],
            $catalog->collectors(new CapturePolicy()),
            'Only an installed package may contribute a collector.',
        );
        self::assertSame(
            ['vite' => VitePanel::class],
            $catalog->panels(),
            'Only an installed package may contribute a panel.',
        );
        self::assertSame(
            ['vite'],
            array_map(static fn(PackagedProvider $provider): string => $provider->id, $catalog->installed()),
            'Only an installed package may be wired.',
        );
    }

    public function testHasReportsADeclaredProviderWhateverItsPackageState(): void
    {
        $catalog = new ProviderCatalog($this->ghost());

        self::assertTrue(
            $catalog->has('ghost'),
            'A declared ID is owned by the catalog.',
        );
        self::assertFalse(
            $catalog->isInstalled('ghost'),
            'An absent package is not installed.',
        );
        self::assertFalse(
            $catalog->has('cache'),
            'An undeclared ID is owned by nobody.',
        );
        self::assertNull(
            $catalog->provider('cache'),
            'An undeclared ID resolves to nothing.',
        );
    }

    public function testPackagedBuildsTheInertiaCollectorWithTheCapturePolicy(): void
    {
        $collectors = ProviderCatalog::packaged()->collectors(new CapturePolicy());

        $inertia = $collectors['inertia'] ?? null;

        self::assertIsArray(
            $inertia,
            'A collector taking arguments needs a configuration array.',
        );
        self::assertSame(
            InertiaCollector::class,
            $inertia['class'] ?? null,
            'Entry must name the collector.',
        );

        $arguments = $inertia['__construct()'] ?? null;

        self::assertIsArray(
            $arguments,
            'Entry must carry the redaction callables.',
        );
        self::assertCount(
            2,
            $arguments,
            'Page props and URLs each need one callable.',
        );
        self::assertSame(
            ViteCollector::class,
            $collectors['vite'] ?? null,
            'A collector taking no arguments needs no configuration array.',
        );
    }

    public function testPackagedDeclaresInertiaBeforeVite(): void
    {
        $catalog = ProviderCatalog::packaged();

        self::assertSame(
            ['inertia', 'vite'],
            array_map(static fn(PackagedProvider $provider): string => $provider->id, $catalog->installed()),
            'Order: Inertia first.',
        );
        self::assertSame(
            ['inertia' => InertiaPanel::class, 'vite' => VitePanel::class],
            $catalog->panels(),
            'Panels must keep the registration order.',
        );
    }

    public function testPackagedDeclaresTheHostComponentOfEachProvider(): void
    {
        $catalog = ProviderCatalog::packaged();

        $inertia = $catalog->provider('inertia');

        self::assertNotNull(
            $inertia,
            'Inertia must be declared.',
        );
        self::assertSame(
            'inertia',
            $inertia->component,
            'Host component must be the Inertia adapter.',
        );
        self::assertSame(
            'yii\inertia\Manager',
            $inertia->componentClass,
            'Component class must be the Yii2 adapter.',
        );
        self::assertSame(
            ProviderAttachment::Property,
            $inertia->attachment,
            'A component exposing a writable dispatcher attaches through the property.',
        );

        $vite = $catalog->provider('vite');

        self::assertNotNull(
            $vite,
            'Vite must be declared.',
        );
        self::assertSame(
            'vite',
            $vite->component,
            'Host component must be the Vite service.',
        );
        self::assertSame(
            'PHPForge\Vite\Vite',
            $vite->componentClass,
            'Component class must be the Vite facade.',
        );
        self::assertSame(
            ProviderAttachment::Constructor,
            $vite->attachment,
            'An immutable service attaches through the constructor.',
        );
    }

    /**
     * Returns a provider whose package no application can install.
     */
    private function ghost(): PackagedProvider
    {
        return new PackagedProvider(
            'ghost',
            'Acme\Debug\GhostCollector',
            'Acme\Debug\GhostPanel',
            'ghost',
            'Acme\Ghost',
            ProviderAttachment::Constructor,
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
