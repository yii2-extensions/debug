<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Panel\Request\{
    RequestDataNormalizer,
    RequestSnapshot,
    RequestToolbarItemFactory,
};
use PHPForge\Debug\Toolbar\ToolbarItem;
use Yii;
use yii\debug\actions\Action as DebugAction;
use yii\debug\Panel;
use yii\debug\panels\request\RequestRoutingViewFactory;
use yii\web\Response;

use function array_map;
use function is_string;

/**
 * Renders the HTTP request and response state captured by the Request collector.
 *
 * Presents the routing target, request/response headers, status code, body, flash messages, and the captured PHP
 * superglobals; data acquisition lives in {@see \yii\debug\collectors\RequestCollector}.
 */
class RequestPanel extends Panel
{
    protected const string ICON = 'request';
    protected const string NAME = 'Request';

    private RequestSnapshot|null $snapshot = null;

    /**
     * Renders one composed request and routing view with a persistent overview and canonical request-data tabs.
     */
    #[Override]
    public function getDetail(): string
    {
        $action = Yii::$app->requestedAction;

        $summary = $action instanceof DebugAction ? $action->summary : null;

        $data = $this->payload();
        $view = RequestDataNormalizer::fromPanelData($data, $summary);
        $router = $this->module->panels['router'] ?? null;
        $router = $router instanceof RouterPanel ? $router : null;

        return Yii::$app->view->render(
            'panels/request/detail',
            [
                'routing' => RequestRoutingViewFactory::fromRequestData(
                    $data,
                    $router?->getSnapshot(),
                    $router?->getError(),
                ),
                'view' => $view,
            ],
            $this,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->snapshot = RequestSnapshot::fromArray($payload, "$.panels.{$this->id}");
    }

    /**
     * Builds one Request toolbar group containing the resolved route followed by the response status code.
     *
     * @return array<int, array<string, mixed>> Route/status items in display order.
     */
    #[Override]
    protected function getToolbarItems(): array
    {
        $route = $this->payload()['route'] ?? null;
        $statusCode = $this->getStatusCode();
        $statusText = Coerce::string(Response::$httpStatuses[$statusCode] ?? null);

        return array_map(
            static fn(ToolbarItem $item): array => $item->jsonSerialize(),
            RequestToolbarItemFactory::create(is_string($route) ? $route : '', $statusCode, $statusText),
        );
    }

    /**
     * Returns the saved response status code, narrowed to an int, defaulting to `200` when missing or non-numeric.
     */
    private function getStatusCode(): int
    {
        return $this->snapshot === null ? 200 : $this->snapshot->statusCode;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function payload(): array
    {
        return $this->snapshot?->data() ?? [];
    }
}
