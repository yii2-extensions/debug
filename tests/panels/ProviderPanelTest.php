<?php

declare(strict_types=1);

namespace yii\debug\tests\panels;

use PHPForge\Debug\{Panel as PortablePanel, PanelView};
use PHPUnit\Framework\TestCase;
use yii\base\InvalidConfigException;
use yii\debug\panels\ProviderPanel;

/**
 * Unit tests for {@see ProviderPanel} adapting a provider-owned declarative panel to the Yii2 debugger.
 */
final class ProviderPanelTest extends TestCase
{
    public function testExternalPanelNeedsNoFrameworkSpecificPresentation(): void
    {
        $panel = new ProviderPanel();
        $panel->id = 'custom';
        $panel->provider = $this->provider();
        self::assertSame('Custom', $panel->getName(), 'Provider title must be preserved.');
        self::assertSame('inertia', $panel->getToolbarIcon(), 'Provider icon must be preserved.');
        self::assertFalse($panel->hasContent(), 'Empty navigation state must be preserved.');
        $panel->hydrate(['hits' => 1]);
        self::assertTrue($panel->hasContent(), 'Hydration must expose captured activity.');
        self::assertStringContainsString('yii-debug-table', $panel->getDetail(), 'External panels must use the shared frontend.');
        $method = new \ReflectionMethod($panel, 'getToolbarItems');
        self::assertSame([['title' => 'Hits', 'value' => '1'], ['title' => 'Misses', 'value' => '2']], $method->invoke($panel), 'Provider metrics must reach the toolbar.');
    }

    public function testThrowInvalidConfigExceptionForMismatchedId(): void
    {
        $panel = new ProviderPanel();
        $panel->provider = $this->provider();
        $this->expectException(InvalidConfigException::class);
        $panel->getName();
    }

    public function testThrowInvalidConfigExceptionForMissingProvider(): void
    {
        $this->expectException(InvalidConfigException::class);
        (new ProviderPanel())->getName();
    }

    private function provider(): PortablePanel
    {
        return new class extends PortablePanel {
            protected const string ICON = 'inertia';
            protected const string ID = 'custom';
            protected const string TITLE = 'Custom';

            public function present(array $data): PanelView
            {
                return PanelView::create()
                    ->summary('', 1)
                    ->overview(['Driver' => 'local'])
                    ->toolbar('Hits', 1)
                    ->toolbar('Misses', 2)
                    ->active($data !== []);
            }
        };
    }
}
