<?php

declare(strict_types=1);

namespace yii\debug\tests\panels;

use PHPForge\Debug\{Panel as PortablePanel, PanelView};
use yii\base\InvalidConfigException;
use yii\debug\Module;
use yii\debug\tests\support\ModuleTestCase;
use yii\debug\tests\support\stub\CustomCollector;

/**
 * Unit tests for {@see Module} validating the IDs a portable collector or panel is registered under.
 */
final class PortableRegistrationTest extends ModuleTestCase
{
    public function testThrowInvalidConfigExceptionForDuplicatePortablePanelIds(): void
    {
        $panel = self::provider();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'Duplicate debug panel ID',
        );

        new Module('debug', null, ['panels' => [$panel, $panel]]);
    }

    public function testThrowInvalidConfigExceptionForMismatchedPortableCollectorKey(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'registration ID must match',
        );

        new Module('debug', null, ['collectors' => ['wrong-key' => new CustomCollector()]]);
    }

    public function testThrowInvalidConfigExceptionForMismatchedPortablePanelKey(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'registration ID must match',
        );

        new Module('debug', null, ['panels' => ['wrong-key' => self::provider()]]);
    }

    /**
     * Builds a minimal provider-owned panel.
     *
     * @return PortablePanel Panel declaring its own ID, icon, and title.
     */
    private static function provider(): PortablePanel
    {
        return new class extends PortablePanel {
            protected const string ICON = 'request';
            protected const string ID = 'portable';
            protected const string TITLE = 'Portable';

            public function present(array $data): PanelView
            {
                return PanelView::create()->active($data !== []);
            }
        };
    }
}
