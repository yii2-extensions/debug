<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Helper\{Coerce, Vocabulary};
use PHPForge\Debug\Panel\Request\{RequestDataNormalizer, RequestSnapshot};
use Yii;
use yii\debug\actions\Action as DebugAction;
use yii\debug\Panel;
use yii\web\Response;

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
     * Renders the detail view with the request hero header and the per-tab sections.
     */
    #[Override]
    public function getDetail(): string
    {
        $action = Yii::$app->requestedAction;

        $summary = $action instanceof DebugAction ? $action->summary : null;

        $view = RequestDataNormalizer::fromPanelData($this->payload(), $summary);

        return Yii::$app->view->render(
            'panels/request/detail',
            ['view' => $view],
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
     * Builds the toolbar item with the response status code, colored by its vocabulary status class
     * (`status-2xx` ... `status-5xx`, or `default` for uncaptured codes).
     *
     * @return array<int, array<string, mixed>> Single-element list with the status chip.
     */
    #[Override]
    protected function getToolbarItems(): array
    {
        $statusCode = $this->getStatusCode();

        $statusClass = Vocabulary::statusClass($statusCode);

        $status = $statusClass === 'none' ? 'default' : "status-{$statusClass}";

        $statusText = Coerce::string(Response::$httpStatuses[$statusCode] ?? null);

        return [
            [
                'status' => $status,
                'title' => "Status code: $statusCode $statusText",
                'value' => $statusCode,
            ],
        ];
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
