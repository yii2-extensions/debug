<?php

declare(strict_types=1);

namespace yii\debug\tests\request;

use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPForge\Debug\Storage\HydrationException;
use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\panels\RequestPanel;
use yii\debug\tests\support\TestCase;
use yii\web\Controller;

/**
 * Unit tests for {@see RequestPanel} covering detail rendering, the toolbar status chip, and snapshot hydration.
 */
#[Group('panel')]
#[Group('request')]
final class RequestPanelTest extends TestCase
{
    public function testGetDetailRendersWithCapturedData(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $this->hydratePanel($panel, RequestSnapshot::capture([
            'route' => 'site/index',
            'statusCode' => 200,
            'general' => ['method' => 'GET'],
            'requestHeaders' => [],
            'responseHeaders' => [],
            'GET' => [],
            'POST' => [],
            'COOKIE' => [],
            'FILES' => [],
            'SERVER' => [],
            'SESSION' => [],
        ]));

        self::assertNotEmpty(
            $panel->getDetail(),
            'Detail view must produce markup.',
        );
    }

    public function testGetDetailUsesEmptySummaryWhenControllerIsNotDefaultController(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        Yii::$app->controller = new Controller('plain', Yii::$app);

        $this->hydratePanel($panel, RequestSnapshot::capture([
            'route' => 'site/index',
            'statusCode' => 200,
            'general' => ['method' => 'GET'],
            'requestHeaders' => [],
            'responseHeaders' => [],
        ]));

        self::assertNotEmpty(
            $panel->getDetail(),
            'Non-default controller must fall back to an empty summary.',
        );
    }

    public function testGetNameAndIcon(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        self::assertSame(
            'Request',
            $panel->getName(),
            "Display name must be 'Request'.",
        );
        self::assertSame(
            'request',
            $panel->getToolbarIcon(),
            "Icon key must be 'request'.",
        );
    }

    public function testGetStatusCodeFallsBackTo200ForNonArrayData(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        self::assertSame(
            200,
            $this->invoke(
                $panel,
                'getStatusCode',
            ),
            "Non-array data must default to '200'.",
        );
    }

    public function testGetStatusCodeReturnsIntStatusCode(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $this->hydratePanel($panel, RequestSnapshot::capture(['statusCode' => 500]));

        self::assertSame(
            500,
            $this->invoke(
                $panel,
                'getStatusCode',
            ),
            'Int status must be returned verbatim.',
        );
    }

    public function testGetToolbarItemsRendersStatus2xxForSuccess(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $this->hydratePanel($panel, RequestSnapshot::capture(['statusCode' => 201]));

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Toolbar items must be an array.',
        );

        $first = $items[0] ?? self::fail('Expected one item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            'status-2xx',
            $first['status'] ?? null,
            "2xx must carry the 'status-2xx' badge.",
        );

        $title = $first['title'] ?? '';

        self::assertIsString(
            $title,
            'Title must be a string.',
        );
        self::assertStringContainsString(
            'Status code: 201',
            $title,
            'Title must include the captured status code.',
        );
    }

    public function testGetToolbarItemsRendersStatus3xxForRedirects(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $this->hydratePanel($panel, RequestSnapshot::capture(['statusCode' => 302]));

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Toolbar items must be an array.',
        );

        $first = $items[0] ?? self::fail('Expected one item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            'status-3xx',
            $first['status'] ?? null,
            "Redirects must carry the 'status-3xx' badge.",
        );
    }

    public function testGetToolbarItemsRendersStatus5xxForServerErrors(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $this->hydratePanel($panel, RequestSnapshot::capture(['statusCode' => 500]));

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Toolbar items must be an array.',
        );

        $first = $items[0] ?? self::fail('Expected one item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            'status-5xx',
            $first['status'] ?? null,
            "Server errors must carry the 'status-5xx' badge.",
        );
        self::assertSame(
            500,
            $first['value'] ?? null,
            'Value must echo the captured status code.',
        );
    }

    public function testGetToolbarItemsTreatsUnknownStatusTextAsEmpty(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $this->hydratePanel($panel, RequestSnapshot::capture(['statusCode' => 299]));

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Toolbar items must be an array.',
        );

        $first = $items[0] ?? self::fail('Expected one item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            'Status code: 299 ',
            $first['title'] ?? null,
            'Unknown status code must render with a blank trailing label.',
        );
    }

    public function testHydrationRejectsNonNumericStatusCode(): void
    {
        $panel = $this->makePanel(RequestPanel::class);
        $payload = RequestSnapshot::capture(['statusCode' => 200])->jsonSerialize();
        $payload['statusCode'] = 'not-a-number';

        $this->expectException(HydrationException::class);

        $panel->hydrate($payload);
    }

    public function testHydrationRejectsNumericStringStatusCode(): void
    {
        $panel = $this->makePanel(RequestPanel::class);
        $payload = RequestSnapshot::capture(['statusCode' => 200])->jsonSerialize();
        $payload['statusCode'] = '404';

        $this->expectException(HydrationException::class);

        $panel->hydrate($payload);
    }

    public function testThrowHydrationExceptionWhenCapturedDataCarriesNoIntegerStatusCode(): void
    {
        $this->expectExceptionMessage("Invalid debug snapshot value at '\$.panels.request.statusCode'");

        RequestSnapshot::capture(['statusCode' => '200']);
    }

    public function testThrowHydrationExceptionWhenTheStatusCodeDisagreesWithTheStoredData(): void
    {
        $payload = RequestSnapshot::capture(['statusCode' => 200])->jsonSerialize();

        $this->expectExceptionMessage("Invalid debug snapshot value at '\$.panels.request.statusCode'");

        RequestSnapshot::fromArray([...$payload, 'statusCode' => 404], '$.panels.request');
    }
}
