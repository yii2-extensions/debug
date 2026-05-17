<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Yii;
use yii\debug\helpers\Coerce;
use yii\debug\models\search\LogSearch;
use yii\debug\Panel;
use yii\log\{Logger, Target};

use function count;
use function is_array;
use function is_string;

/**
 * Captures error, warning, info, and trace log messages emitted during the request and renders them in the Logs panel.
 *
 * Skips categories owned by the Router panel (to avoid duplicate rows in the routing trace) and decorates each row
 * with the previous/next message ids and the time-since-previous delta, so the detail view can render the navigation
 * buttons on each row.
 *
 * @extends Panel<array{messages?: mixed}>
 */
class LogPanel extends Panel
{
    /**
     * @var array<int, array{
     *   id: int,
     *   message: mixed,
     *   level: int,
     *   category: string,
     *   time: float,
     *   time_of_previous: float,
     *   time_since_previous: float,
     *   id_of_previous: int|null,
     *   id_of_next: int|null,
     *   trace: array<int, array<string, mixed>>
     * }>|null Cached typed log rows consumed by the logs grid.
     */
    private array|null $models = null;

    /**
     * Renders the detail view with the logs grid.
     */
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
     * Returns the panel display name.
     */
    public function getName(): string
    {
        return 'Logs';
    }

    /**
     * Renders the toolbar summary chip with the per-level counts (error / warning / info).
     */
    public function getSummary(): string
    {
        return Yii::$app->view->render(
            'panels/log/summary',
            [
                'data' => ['messages' => $this->getSavedMessages()],
                'panel' => $this,
            ],
            $this,
        );
    }

    /**
     * Returns the toolbar icon name.
     */
    public function getToolbarIcon(): string
    {
        return 'logs';
    }

    /**
     * Captures every error/warning/info/trace log message, excluding the categories owned by the Router panel.
     *
     * @return array{messages: array<int, array<int|string, mixed>>} Saved payload consumed by {@see getSavedMessages()}
     * on read-back.
     */
    public function save(): array
    {
        $except = [];

        $routerPanel = $this->module?->panels['router'] ?? null;

        if ($routerPanel instanceof RouterPanel) {
            $except = self::normalizeStringList($routerPanel->getCategories());
        }

        $messages = $this->getLogMessages(
            Logger::LEVEL_ERROR | Logger::LEVEL_INFO | Logger::LEVEL_WARNING | Logger::LEVEL_TRACE,
            [],
            $except,
            true,
        );

        return ['messages' => $messages];
    }

    /**
     * Builds and caches the typed log rows consumed by the logs grid.
     *
     * Decorates each row with `id`, the previous/next row ids, and the time delta since the previous row. Suitable for
     * {@see \yii\data\ArrayDataProvider}.
     *
     * @param bool $refresh `true` to rebuild the cache from the saved messages.
     *
     * @return array<int, array{
     *   id: int,
     *   message: mixed,
     *   level: int,
     *   category: string,
     *   time: float,
     *   time_of_previous: float,
     *   time_since_previous: float,
     *   id_of_previous: int|null,
     *   id_of_next: int|null,
     *   trace: array<int, array<string, mixed>>
     * }> Log rows indexed by `id`, with `time` and `time_of_previous` in milliseconds.
     */
    protected function getModels(bool $refresh = false): array
    {
        if ($this->models === null || $refresh) {
            $models = [];

            $messages = $this->getSavedMessages();

            $messageCount = count($messages);

            $previousId = null;
            $previousTime = null;

            foreach ($messages as $index => $message) {
                $id = $index + 1;

                $timestamp = Coerce::floatOrNull($message[3] ?? null) ?? 0.0;

                if (null === $previousTime) {
                    $previousTime = $timestamp;
                }

                $models[$id] = [
                    'id' => $id,
                    'message' => $message[0] ?? null,
                    'level' => Coerce::intOrNull($message[1] ?? null) ?? 0,
                    'category' => Coerce::stringOrNull($message[2] ?? null) ?? '',
                    'time' => $timestamp * 1000, // time in milliseconds
                    'time_of_previous' => $previousTime * 1000, // time in milliseconds
                    'time_since_previous' => $timestamp - $previousTime,
                    'id_of_previous' => $previousId,
                    'id_of_next' => $id < $messageCount ? $id + 1 : null,
                    'trace' => Coerce::traceFrames($message[4] ?? []),
                ];
                $previousId = $id;
                $previousTime = $timestamp;
            }

            $this->models = $models;
        }

        return $this->models;
    }

    /**
     * Builds the toolbar items: the total message count plus per-level chips (errors in `danger`, warnings in
     * `warning`) when those levels surfaced at least one message.
     *
     * @return array<int, array<string, mixed>> Toolbar items in display order.
     */
    protected function getToolbarItems(): array
    {
        $messages = $this->getSavedMessages();

        $messageCount = count($messages);
        $errorCount = count(Target::filterMessages($messages, Logger::LEVEL_ERROR));
        $warningCount = count(Target::filterMessages($messages, Logger::LEVEL_WARNING));

        $items = [
            ['value' => $messageCount],
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

    /**
     * Returns the saved log messages, dropping any non-array entries.
     *
     * @return array<int, array<int|string, mixed>> Saved messages in capture order.
     */
    private function getSavedMessages(): array
    {
        $data = is_array($this->data) ? $this->data : [];

        $messages = $data['messages'] ?? [];

        if (!is_array($messages)) {
            return [];
        }

        $normalized = [];

        foreach ($messages as $message) {
            if (is_array($message)) {
                $normalized[] = $message;
            }
        }

        return $normalized;
    }

    /**
     * Narrows a mixed payload into a list of strings, dropping non-string entries.
     *
     * @param mixed $values Raw category list.
     *
     * @return array<int, string> String entries in original order, possibly empty.
     */
    private static function normalizeStringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $value) {
            if (is_string($value)) {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }
}
