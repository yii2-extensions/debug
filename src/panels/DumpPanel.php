<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Closure;
use Stringable;
use Yii;
use yii\debug\models\search\Log;
use yii\debug\Panel;
use yii\helpers\Html;
use yii\helpers\VarDumper;
use yii\log\Logger;

use function array_key_exists;
use function count;
use function is_array;
use function is_float;
use function is_int;
use function is_scalar;
use function is_string;

/**
 * Dump panel that collects and displays debug messages (Logger::LEVEL_TRACE).
 */
class DumpPanel extends Panel
{
    /**
     * @var array<int, string> Message categories to filter by. If empty array, it means all categories are allowed
     */
    public array $categories = ['application'];
    /**
     * Maximum depth that the dumper should go into the variable
     */
    public int $depth = 10;
    /**
     * Whether the result should be syntax-highlighted
     */
    public bool $highlight = true;
    /**
     * @var Closure(mixed, self): string|null Callback that replaces the built-in var dumper.
     */
    public Closure|null $varDumpCallback = null;

    /**
     * @var array<int, array{
     *   message: string,
     *   level: int,
     *   category: string,
     *   time: float,
     *   trace: array<int, array<string, mixed>>
     * }>|null Log messages extracted to array as models, to use with data provider.
     */
    private array|null $models = null;

    public function getDetail(): string
    {
        $searchModel = new Log();

        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams(), $this->getModels());

        return Yii::$app->view->render(
            'panels/dump/detail',
            [
                'dataProvider' => $dataProvider,
                'panel' => $this,
                'searchModel' => $searchModel,
            ],
        );
    }

    public function getName(): string
    {
        return 'Dump';
    }

    public function getSummary(): string
    {
        return Yii::$app->view->render('panels/dump/summary', ['panel' => $this]);
    }

    public function getToolbarIcon(): string
    {
        return 'dump';
    }

    /**
     * @return array<int, array<int|string, mixed>>
     */
    public function save(): array
    {
        $except = [];

        $routerPanel = $this->module?->panels['router'] ?? null;

        if ($routerPanel instanceof RouterPanel) {
            $except = self::normalizeStringList($routerPanel->getCategories());
        }

        $messages = $this->getLogMessages(Logger::LEVEL_TRACE, $this->categories, $except);

        foreach ($messages as &$message) {
            if (array_key_exists(0, $message) === false) {
                continue;
            }

            $message[0] = $this->varDump($message[0]);
        }

        return $messages;
    }

    /**
     * Called by `save()` to format the dumped variable.
     */
    public function varDump(mixed $var): string
    {
        if ($this->varDumpCallback !== null) {
            return ($this->varDumpCallback)($var, $this);
        }

        $message = VarDumper::dumpAsString($var, $this->depth, $this->highlight);

        //don't encode highlighted variables
        if (!$this->highlight) {
            $message = Html::encode($message);
        }

        return $message;
    }

    /**
     * Returns an array of models that represents logs of the current request.
     *
     * Can be used with data providers, such as {@see \yii\data\ArrayDataProvider}.
     *
     * @param bool $refresh if need to build models from log messages and refresh them.
     *
     * @return array<int, array{
     *   message: string,
     *   level: int,
     *   category: string,
     *   time: float,
     *   trace: array<int, array<string, mixed>>
     * }>
     */
    protected function getModels(bool $refresh = false): array
    {
        if ($this->models === null || $refresh) {
            $this->models = [];

            $messages = is_array($this->data) ? $this->data : [];

            foreach ($messages as $message) {
                $model = self::normalizeMessage($message);

                if ($model !== null) {
                    $this->models[] = $model;
                }
            }
        }

        return $this->models;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function getToolbarItems(): array|null
    {
        $messages = is_array($this->data) ? $this->data : [];

        if ($messages === []) {
            return null;
        }

        return [
            [
                'status' => 'info',
                'title' => 'Number of dumped variables',
                'value' => count($messages),
            ],
        ];
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
     * @param mixed $message Raw log message from saved panel data.
     *
     * @return array{
     *   message: string,
     *   level: int,
     *   category: string,
     *   time: float,
     *   trace: array<int, array<string, mixed>>
     * }|null
     */
    private static function normalizeMessage(mixed $message): array|null
    {
        if (!is_array($message)) {
            return null;
        }

        return [
            'message' => self::stringValue($message[0] ?? null) ?? '',
            'level' => self::intValue($message[1] ?? null) ?? 0,
            'category' => self::stringValue($message[2] ?? null) ?? '',
            'time' => (self::floatValue($message[3] ?? null) ?? 0.0) * 1000,
            'trace' => self::normalizeTrace($message[4] ?? []),
        ];
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
