<?php

declare(strict_types=1);

namespace yii\debug\tests\panels;

use Acme\Debug\{Cache, CacheCollector, CachePanel};
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary, SnapshotStore};
use yii\debug\{LogTarget, Module};
use yii\debug\panels\ProviderPanel;
use yii\debug\tests\support\ModuleTestCase;
use yii\debug\widgets\sidebar\SidebarDataNormalizer;

final class ExternalCacheTest extends ModuleTestCase
{
    public function testExternalPackagesCaptureReplayAndRegisterASecondIdWithoutHostChanges(): void
    {
        \Yii::$app->getRequest()->setUrl('/cache');
        $providers = [new CachePanel(), new class extends CachePanel {
            protected const string ID = 'independent-second-cache';
            protected const string TITLE = 'Secondary cache';
            protected const string ICON = 'unavailable-custom-icon';
        }];
        foreach ($providers as $provider) {
            $collector = new CacheCollector($provider->id(), new \yii\log\PsrLogger(category: 'acme.cache'));
            $cache = new Cache($collector);
            $path = sys_get_temp_dir() . '/external-yii2-' . uniqid();
            $host = new Module('debug', null, [
                'dataPath' => $path, 'collectors' => [$collector], 'panels' => [$provider],
            ]);
            $coordinator = $host->getCollectorCoordinator();
            $coordinator->startup();
            self::assertNull($cache->get('historical-key'));
            $cache->set('historical-key', 'private contents');
            self::assertSame('private contents', $cache->get('historical-key'));
            $target = new LogTarget($host);
            $target->export();
            self::assertNull($collector->capture());
            $tag = array_key_first($target->loadManifest());
            self::assertNotNull($tag);
            $cache->set('historical-key', 'changed after capture');
            unset($host, $target, $cache);

            // Fresh host, fresh collector lifecycle, and an unrelated live service.
            $freshCollector = new CacheCollector($provider->id(), new \Psr\Log\NullLogger());
            $fresh = new Module('debug', null, [
                'dataPath' => $path, 'collectors' => [$freshCollector], 'panels' => [$provider],
            ]);
            $target = new LogTarget($fresh);
            $summary = $target->loadTagToPanels($tag);
            self::assertNotNull($summary);
            $panel = $fresh->panels[$provider->id()] ?? null;
            self::assertInstanceOf(ProviderPanel::class, $panel);
            self::assertSame($provider->name(), $panel->getName());
            self::assertSame($provider->icon(), $panel->getToolbarIcon());
            self::assertStringContainsString('historical-key', $panel->getDetail());
            self::assertStringNotContainsString('changed after capture', $panel->getDetail());
            self::assertStringContainsString('"value":"1"', json_encode($panel->getToolbarData(), JSON_THROW_ON_ERROR));
            $sidebar = SidebarDataNormalizer::fromView($fresh->panels, $target->loadManifest(), $panel, $tag, $summary);
            self::assertContains($provider->name(), array_map(static fn($item) => $item->label, $sidebar->navGroups['Extensions'] ?? []));

            $store = new SnapshotStore($path, 0o700, 0o600);
            $store->writeSnapshot(new DebugSnapshot(RequestSummary::create('bad'), [$provider->id() => ['schema' => 9]], []), 10);
            self::assertNotNull($target->loadTagToPanels('bad'));
            self::assertTrue($panel->hasError());
            self::assertTrue($panel->hasContent());
            self::assertStringContainsString('error', json_encode($panel->getToolbarData(), JSON_THROW_ON_ERROR));
            $badSidebar = SidebarDataNormalizer::fromView($fresh->panels, [], $panel, 'bad', RequestSummary::create('bad'));
            self::assertSame($provider->name(), $badSidebar->navGroups['Extensions'][0]->label ?? null);
            $panel->hydrate(['schema' => 1, 'operations' => []]);
            self::assertFalse($panel->hasError());
            self::assertStringContainsString('No cache operations', $panel->getDetail());
            $fresh->getCollectorCoordinator()->run(static function (): void {});
            self::assertNull($freshCollector->capture());
            $collectorOnly = new Module('debug', null, ['dataPath' => $path]);
            $fallback = new LogTarget($collectorOnly);
            self::assertNotNull($fallback->loadTagToPanels($tag));
            $rawPanel = $collectorOnly->panels[$provider->id()] ?? null;
            self::assertInstanceOf(\yii\debug\panels\JsonPanel::class, $rawPanel);
            self::assertStringContainsString('historical-key', $rawPanel->getDetail());
            $panelOnly = new Module('debug', null, ['dataPath' => $path, 'panels' => [$provider]]);
            self::assertFalse($panelOnly->getCollectorCoordinator()->hasCollector($provider->id()));
            self::assertNotNull((new LogTarget($panelOnly))->loadTagToPanels($tag));
            self::assertInstanceOf(ProviderPanel::class, $panelOnly->panels[$provider->id()] ?? null);
            $store->clear();
        }
    }
    public function testRejectsDuplicatePortablePanelIds(): void
    {
        $panel = new CachePanel();
        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('Duplicate debug panel ID');
        new Module('debug', null, ['panels' => [$panel, $panel]]);
    }

    public function testRejectsMismatchedPortableCollectorKey(): void
    {
        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('registration ID must match');
        new Module('debug', null, ['collectors' => ['wrong-key' => new CacheCollector((new CachePanel())->id(), new \Psr\Log\NullLogger())]]);
    }

    public function testRejectsMismatchedPortablePanelKey(): void
    {
        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('registration ID must match');
        new Module('debug', null, ['panels' => ['wrong-key' => new CachePanel()]]);
    }
}
