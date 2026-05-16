<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Closure;
use UIAwesome\Html\Helper\Encode;
use Yii;
use yii\debug\helpers\Coerce;
use yii\debug\models\search\LogSearch;
use yii\debug\Panel;
use yii\helpers\VarDumper;
use yii\log\Logger;

use function array_key_exists;
use function count;
use function is_array;
use function is_string;

/**
 * Captures trace-level log messages emitted by `Yii::debug()` and renders them as dump cards.
 *
 * Filters the trace log by {@see $categories} (and skips categories owned by the Router panel) and stringifies each
 * captured value through {@see varDump()}, so the detail view can render the result without re-serializing.
 *
 * @extends Panel<array<int, array<int|string, mixed>>>
 */
class DumpPanel extends Panel
{
    /**
     * @var array<int, string> Message categories to capture; an empty list captures every category.
     */
    public array $categories = ['application'];
    /**
     * Maximum recursion depth applied by the dumper.
     */
    public int $depth = 10;
    /**
     * Whether the rendered dump should be syntax-highlighted.
     */
    public bool $highlight = true;
    /**
     * @var Closure(mixed, self): string|null Callback that replaces the built-in {@see VarDumper} rendering when set.
     */
    public Closure|null $varDumpCallback = null;

    /**
     * @var array<int, array{
     *   message: string,
     *   level: int,
     *   category: string,
     *   time: float,
     *   trace: array<int, array<string, mixed>>
     * }>|null Cached typed rows consumed by the dumps grid.
     */
    private array|null $models = null;

    /**
     * Renders the detail view with the dump grid powered by the Log search model.
     */
    public function getDetail(): string
    {
        $searchModel = new LogSearch();

        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams(), $this->getModels());

        return Yii::$app->view->render(
            'panels/dump/detail',
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
        return 'Dump';
    }

    /**
     * Renders the toolbar summary chip.
     */
    public function getSummary(): string
    {
        return Yii::$app->view->render(
            'panels/dump/summary',
            ['panel' => $this],
            $this,
        );
    }

    /**
     * Returns the toolbar icon name.
     */
    public function getToolbarIcon(): string
    {
        return 'dump';
    }

    /**
     * Captures the trace-level messages allowed by {@see $categories}, excluding the categories owned by the Router
     * panel, and pre-renders each captured value through {@see varDump()}.
     *
     * @return array<int, array<int|string, mixed>> Raw log tuples with the first element pre-rendered as a string.
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
     * Renders a captured value as a display string.
     *
     * The highlighter emits safe markup, so highlighted output is passed through unchanged; plain output is
     * HTML-escaped explicitly.
     */
    public function varDump(mixed $var): string
    {
        if ($this->varDumpCallback !== null) {
            return ($this->varDumpCallback)($var, $this);
        }

        $message = VarDumper::dumpAsString($var, $this->depth, $this->highlight);

        if (!$this->highlight) {
            $message = Encode::content($message);
        }

        return $message;
    }

    /**
     * Builds and caches the typed dump rows consumed by the dumps grid.
     *
     * Suitable for {@see \yii\data\ArrayDataProvider}.
     *
     * @param bool $refresh `true` to rebuild the cache from the saved messages.
     *
     * @return array<int, array{
     *   message: string,
     *   level: int,
     *   category: string,
     *   time: float,
     *   trace: array<int, array<string, mixed>>
     * }> Dump rows in capture order, with `time` in milliseconds.
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
     * Returns the toolbar item showing the number of dumped variables, or `null` when none were captured.
     *
     * @return array<int, array<string, mixed>>|null Single-element list with the `info` chip, or `null`.
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

    /**
     * Narrows one raw saved log tuple into the typed dump-row shape, or returns `null` when the entry is not an array.
     *
     * @param mixed $message Raw log message from saved panel data.
     *
     * @return array{
     *   message: string,
     *   level: int,
     *   category: string,
     *   time: float,
     *   trace: array<int, array<string, mixed>>
     * }|null Typed dump row with `time` in milliseconds, or `null` when the entry was malformed.
     */
    private static function normalizeMessage(mixed $message): array|null
    {
        if (!is_array($message)) {
            return null;
        }

        return [
            'message' => Coerce::stringOrNull($message[0] ?? null) ?? '',
            'level' => Coerce::intOrNull($message[1] ?? null) ?? 0,
            'category' => Coerce::stringOrNull($message[2] ?? null) ?? '',
            'time' => (Coerce::floatOrNull($message[3] ?? null) ?? 0.0) * 1000,
            'trace' => Coerce::traceFrames($message[4] ?? []),
        ];
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
