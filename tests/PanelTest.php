<?php

declare(strict_types=1);

namespace yiiunit\debug;

use yii\debug\Module;
use yii\debug\Panel;

class PanelTest extends TestCase
{
    public function testGetTraceLine_DefaultLink(): void
    {
        $traceConfig = [
            'file' => 'file.php',
            'line' => 10,
        ];
        $panel = $this->getPanel();
        $this->assertEquals('<a href="ide://open?url=file://file.php&line=10">file.php:10</a>', $panel->getTraceLine($traceConfig));
    }

    public function testGetTraceLine_DefaultLink_CustomText(): void
    {
        $traceConfig = [
            'file' => 'file.php',
            'line' => 10,
            'text' => 'custom text',
        ];
        $panel = $this->getPanel();
        $this->assertEquals(
            '<a href="ide://open?url=file://file.php&line=10">custom text</a>',
            $panel->getTraceLine($traceConfig)
        );
    }

    public function testGetTraceLine_TextOnly(): void
    {
        $panel = $this->getPanel();
        $panel->module->traceLine = false;
        $traceConfig = [
            'file' => 'file.php',
            'line' => 10,
        ];
        $this->assertEquals('file.php:10', $panel->getTraceLine($traceConfig));
    }

    public function testGetTraceLine_CustomLinkByString(): void
    {
        $traceConfig = [
            'file' => 'file.php',
            'line' => 10,
        ];
        $panel = $this->getPanel();
        $panel->module->traceLine = '<a href="phpstorm://open?url=file://file.php&line=10">my custom phpstorm protocol</a>';
        $this->assertEquals(
            '<a href="phpstorm://open?url=file://file.php&line=10">my custom phpstorm protocol</a>',
            $panel->getTraceLine($traceConfig)
        );
    }

    public function testGetTraceLine_CustomLinkByCallback(): void
    {
        $traceConfig = [
            'file' => 'file.php',
            'line' => 10,
        ];
        $panel = $this->getPanel();
        $expected = 'http://my.custom.link';
        $panel->module->traceLine = fn() => $expected;
        $this->assertEquals($expected, $panel->getTraceLine($traceConfig));
    }

    public function testGetTraceLine_CustomLinkByCallback_CustomText(): void
    {
        $traceConfig = [
            'file' => 'file.php',
            'line' => 10,
            'text' => 'custom text',
        ];
        $panel = $this->getPanel();
        $panel->module->traceLine = fn() => '<a href="ide://open?url={file}&line={line}">{text}</a>';
        $this->assertEquals(
            '<a href="ide://open?url=file.php&line=10">custom text</a>',
            $panel->getTraceLine($traceConfig)
        );
    }

    public function testGetTraceLine_tracePathMappings(): void
    {
        $traceConfig = [
            'file' => '/app/file.php',
            'line' => 10,
        ];
        $panel = $this->getPanel();
        $panel->module->tracePathMappings = [
            '/app' => '/newpath/', // intentional mismatch of trailing slashes
        ];
        $this->assertEquals(
            '<a href="ide://open?url=file:///newpath/file.php&line=10">/app/file.php:10</a>',
            $panel->getTraceLine($traceConfig)
        );
    }

    public function testGetTraceLine_tracePathMappings_Multiple(): void
    {
        $traceConfig = [
            'file' => '/app/data/file.php',
            'line' => 10,
        ];
        $panel = $this->getPanel();
        $panel->module->tracePathMappings = [
            '/app/data' => '/app/localdata',
            '/app' => '/newpath',
        ];
        $this->assertEquals(
            '<a href="ide://open?url=file:///app/localdata/file.php&line=10">/app/data/file.php:10</a>',
            $panel->getTraceLine($traceConfig)
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockWebApplication();
    }

    private function getPanel()
    {
        return new Panel(['module' => new Module('debug')]);
    }
}
