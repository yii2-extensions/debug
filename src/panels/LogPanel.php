<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Stringable;
use Yii;
use yii\debug\models\search\Log;
use yii\debug\Panel;
use yii\log\{Logger, Target};

use function count;
use function is_array;
use function is_float;
use function is_int;
use function is_scalar;
use function is_string;

/**
 * Debugger panel that collects and displays logs.
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
     * }>|null Log messages extracted to array as models, to use with data provider.
     */
    private array|null $models = null;

    public function getDetail(): string
    {
        $searchModel = new Log();

        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams(), $this->getModels());

        return Yii::$app->view->render(
            'panels/log/detail',
            [
                'dataProvider' => $dataProvider,
                'panel' => $this,
                'searchModel' => $searchModel,
            ],
        );
    }

    public function getName(): string
    {
        return 'Logs';
    }

    public function getSummary(): string
    {
        return Yii::$app->view->render(
            'panels/log/summary',
            [
                'data' => ['messages' => $this->getSavedMessages()],
                'panel' => $this,
            ],
        );
    }

    public function getToolbarIcon(): string
    {
        return 'logs';
    }

    /**
     * @return array{messages: array<int, array<int|string, mixed>>}
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
     * Returns an array of models that represents logs of the current request.
     *
     * Can be used with data providers, such as {@see \yii\data\ArrayDataProvider}.
     *
     * @param bool $refresh if need to build models from log messages and refresh them.
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
     * }>
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

                $timestamp = self::floatValue($message[3] ?? null) ?? 0.0;

                if (null === $previousTime) {
                    $previousTime = $timestamp;
                }

                $models[$id] = [
                    'id' => $id,
                    'message' => $message[0] ?? null,
                    'level' => self::intValue($message[1] ?? null) ?? 0,
                    'category' => self::stringValue($message[2] ?? null) ?? '',
                    'time' => $timestamp * 1000, // time in milliseconds
                    'time_of_previous' => $previousTime * 1000, // time in milliseconds
                    'time_since_previous' => $timestamp - $previousTime,
                    'id_of_previous' => $previousId,
                    'id_of_next' => $id < $messageCount ? $id + 1 : null,
                    'trace' => self::normalizeTrace($message[4] ?? []),
                ];
                $previousId = $id;
                $previousTime = $timestamp;
            }

            $this->models = $models;
        }

        return $this->models;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getToolbarItems(): array
    {
        $messages = $this->getSavedMessages();

        $messageCount = count($messages);
        $errorCount = count(Target::filterMessages($messages, Logger::LEVEL_ERROR));
        $warningCount = count(Target::filterMessages($messages, Logger::LEVEL_WARNING));

        $items = [
            [
                'value' => $messageCount,
            ],
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

    private static function floatValue(mixed $value): float|null
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    /**
     * @return array<int, array<int|string, mixed>>
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

    private static function intValue(mixed $value): int|null
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param mixed $values Raw category list.
     *
     * @return array<int, string>
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

    /**
     * @param mixed $trace Raw trace from a log message.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeTrace(mixed $trace): array
    {
        if (!is_array($trace)) {
            return [];
        }

        $normalized = [];

        foreach ($trace as $frame) {
            if (!is_array($frame)) {
                continue;
            }

            $normalizedFrame = [];

            foreach ($frame as $key => $value) {
                if (is_string($key)) {
                    $normalizedFrame[$key] = $value;
                }
            }

            $normalized[] = $normalizedFrame;
        }

        return $normalized;
    }

    private static function stringValue(mixed $value): string|null
    {
        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return null;
    }
}
