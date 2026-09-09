<?php

declare(strict_types=1);

namespace yii\debug\tests\actions;

use PHPForge\Debug\Panel\Config\ConfigSnapshot;
use PHPForge\Debug\Panel\Db\DbSnapshot;
use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPUnit\Framework\Attributes\Group;
use Xepozz\InternalMocker\MockerState;
use Yii;
use yii\debug\actions\ToolbarDataAction;
use yii\debug\tests\support\ActionTestCase;
use yii\debug\tests\support\stub\{MinimalToolbarPanel, StubSnapshot};
use yii\helpers\Url;
use yii\web\AssetManager;

use function is_array;

/**
 * Unit tests for {@see ToolbarDataAction} covering metadata payloads and published asset URLs, incomplete panel-envelope
 * defaults, the JSON 404 response after missing-tag retries, and asset-manager failure handling.
 */
#[Group('actions')]
final class ToolbarDataActionTest extends ActionTestCase
{
    public function testActionToolbarDataInjectsDefaultsForIncompletePanelEnvelopes(): void
    {
        $module = $this->bootDebugModule();

        // Wire a panel whose 'getToolbarData()' omits 'id', 'title', and 'url' so the controller's defaults kick in.
        $stub = new MinimalToolbarPanel();

        $stub->id = 'stub';
        $stub->module = $module;
        $module->panels['stub'] = $stub;

        $this->writeDebugSnapshot(
            $module,
            'tag-toolbar-stub',
            ['stub' => StubSnapshot::capture([])],
        );

        $result = $this->runDebugAction(
            new ToolbarDataAction('toolbar-data'),
            $module,
            ['tag' => 'tag-toolbar-stub'],
        );

        self::assertIsArray(
            $result,
            'Payload must decode to an array.',
        );

        $items = $result['items'] ?? [];

        self::assertIsArray(
            $items,
            "'items' must be a list.",
        );
        self::assertNotSame(
            [],
            $items,
            "Payload must expose 'items' for the wired panels.",
        );

        $stubItem = null;

        foreach ($items as $item) {
            if (is_array($item) && ($item['id'] ?? null) === 'stub') {
                $stubItem = $item;
                break;
            }
        }

        self::assertNotNull(
            $stubItem,
            "Stub panel chip must surface in 'items'.",
        );
        self::assertArrayHasKey(
            'title',
            $stubItem,
            "'title' must be injected when missing.",
        );
        self::assertArrayHasKey(
            'url',
            $stubItem,
            "'url' must be injected when missing.",
        );
    }

    public function testActionToolbarDataReturnsJsonErrorWhenTagIsUnknown(): void
    {
        $module = $this->bootDebugModule();

        MockerState::addCondition(
            'yii\debug\actions',
            'sleep',
            [],
            0,
            true,
        );

        $this->writeDebugSnapshot(
            $module,
            'tag-toolbar',
            [],
        );

        $result = $this->runDebugAction(
            new ToolbarDataAction('toolbar-data'),
            $module,
            ['tag' => 'does-not-exist'],
        );

        self::assertIsArray(
            $result,
            'Payload must decode to an array.',
        );
        self::assertSame(
            'Debug tag not found.',
            $result['error'] ?? null,
            'Rotated tag must surface as a JSON error envelope.',
        );
        self::assertSame(
            404,
            Yii::$app->response->getStatusCode(),
            "Response must emit a '404' status code.",
        );
        self::assertSame(
            array_fill(0, 5, [1]),
            array_column(MockerState::getTraces('yii\debug\actions', 'sleep'), 'arguments'),
            'Toolbar metadata must retry five times with a one-second wait between attempts.',
        );
    }

    public function testActionToolbarDataReturnsMetadataPayloadForKnownTag(): void
    {
        $module = $this->bootDebugModule();

        // Persist data for every panel that surfaces toolbar chips so the controller's panel iteration runs end-to-end.
        $this->writeDebugSnapshot(
            $module,
            'tag-toolbar-ok',
            [
                'config' => ConfigSnapshot::capture([]),
                'db' => new DbSnapshot([]),
                'log' => LogSnapshot::capture([]),
                'request' => RequestSnapshot::capture(['statusCode' => 200]),
            ],
        );

        $result = $this->runDebugAction(
            new ToolbarDataAction('toolbar-data'),
            $module,
            ['tag' => 'tag-toolbar-ok'],
        );

        self::assertIsArray(
            $result,
            'Payload must decode to an array.',
        );
        self::assertSame(
            'tag-toolbar-ok',
            $result['tag'] ?? null,
            'Payload must echo the active tag.',
        );
        self::assertSame(
            Url::toRoute(['/debug/view', 'tag' => 'tag-toolbar-ok', 'panel' => 'config']),
            $result['configUrl'] ?? null,
            "'configUrl' must deep-link the Config panel for the active tag.",
        );

        $iconBaseUrl = $result['iconBaseUrl'] ?? null;

        self::assertIsString(
            $iconBaseUrl,
            "'iconBaseUrl' must be a string.",
        );
        self::assertStringStartsWith(
            '/assets/',
            $iconBaseUrl,
            "'iconBaseUrl' must use the published asset URL, not its filesystem path.",
        );
        self::assertStringEndsWith(
            '/svg/',
            $iconBaseUrl,
            "'iconBaseUrl' must point to the published SVG directory.",
        );
        self::assertSame(
            "{$iconBaseUrl}yii.svg",
            $result['logo'] ?? null,
            'Published Yii logo must use the SVG asset URL.',
        );
    }

    public function testActionToolbarDataSwallowsAssetManagerFailure(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-asset-fail',
            ['config' => ConfigSnapshot::capture([])],
        );

        // Replace the asset manager with one whose 'basePath' points at a non-writable path so 'publish()' throws.
        Yii::$app->set(
            'assetManager',
            new AssetManager(['basePath' => '/dev/null/does-not-exist', 'baseUrl' => '/x']),
        );

        $result = $this->runDebugAction(
            new ToolbarDataAction('toolbar-data'),
            $module,
            ['tag' => 'tag-asset-fail'],
        );

        self::assertIsArray(
            $result,
            'Payload must decode to an array.',
        );
        self::assertSame(
            '',
            $result['iconBaseUrl'] ?? null,
            "Failed publish must leave 'iconBaseUrl' empty.",
        );
    }
}
