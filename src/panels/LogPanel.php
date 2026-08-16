<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Helper\Coerce;
use Yii;
use yii\debug\models\search\LogSearch;
use yii\debug\Panel;
use yii\debug\panels\log\{LogCounts, LogRow, LogSnapshot};
use yii\log\Logger;

use function array_map;

/**
 * Captures error, warning, info, and trace log messages emitted during the request and renders them in the Logs panel.
 *
 * Skips categories owned by the Router panel (to avoid duplicate rows in the routing trace) and decorates each row
 * with the previous/next message ids and the time-since-previous delta, so the detail view can render the navigation
 * buttons on each row.
 */
class LogPanel extends Panel implements ProvidesMemorySamples
{
    protected const string ICON = 'logs';
    protected const string NAME = 'Logs';

    private LogSnapshot|null $snapshot = null;

    /**
     * Captures every error/warning/info/trace log message, excluding the categories owned by the Router panel.
     */
    public function capture(): LogSnapshot
    {
        $except = [];

        $routerPanel = $this->module?->panels['router'] ?? null;

        if ($routerPanel instanceof RouterPanel) {
            $except = Coerce::stringList($routerPanel->getCategories());
        }

        $messages = $this->getLogMessages(
            Logger::LEVEL_ERROR | Logger::LEVEL_INFO | Logger::LEVEL_WARNING | Logger::LEVEL_TRACE,
            [],
            $except,
            true,
        );

        return LogSnapshot::capture($messages);
    }

    /**
     * Renders the detail view with the logs grid.
     */
    #[Override]
    public function getDetail(): string
    {
        $searchModel = new LogSearch();

        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams(), $this->getModels());

        return Yii::$app->view->render(
            'panels/log/detail',
            [
                'dataProvider' => $dataProvider,
                'panel' => $this,
                'searchModel' => $searchModel,
            ],
            $this,
        );
    }

    /**
     * @return list<MemorySample> Memory readings recorded alongside each captured log message.
     */
    public function getMemorySamples(): array
    {
        return array_map(
            static fn(LogRow $row): MemorySample => new MemorySample($row->time, $row->memory),
            $this->getMessages(),
        );
    }

    /**
     * @return list<LogRow> Captured log rows in capture order.
     */
    public function getMessages(): array
    {
        return $this->snapshot?->entries() ?? [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->snapshot = LogSnapshot::fromArray($payload, "$.panels.{$this->id}");
    }

    /**
     * Returns the typed log rows consumed by the logs grid.
     *
     * @return list<LogRow> Rows in capture order, suitable for {@see \yii\data\ArrayDataProvider}.
     */
    protected function getModels(): array
    {
        return $this->getMessages();
    }

    /**
     * Builds the toolbar items: the total message count plus per-level chips (errors in `danger`, warnings in
     * `warning`) when those levels surfaced at least one message.
     *
     * @return array<int, array<string, mixed>> Toolbar items in display order.
     */
    #[Override]
    protected function getToolbarItems(): array
    {
        $counts = LogCounts::fromRows($this->getMessages());

        $errorCount = $counts->errors;
        $warningCount = $counts->warnings;

        $items = [
            ['value' => $counts->total],
        ];

        if ($errorCount > 0) {
            $items[] = [
                'label' => 'Errors',
                'status' => 'danger',
                'url' => $this->getUrl(['Log[level]' => Logger::LEVEL_ERROR]),
                'value' => $errorCount,
            ];
        }

        if ($warningCount > 0) {
            $items[] = [
                'label' => 'Warnings',
                'status' => 'warning',
                'url' => $this->getUrl(['Log[level]' => Logger::LEVEL_WARNING]),
                'value' => $warningCount,
            ];
        }

        return $items;
    }
}
