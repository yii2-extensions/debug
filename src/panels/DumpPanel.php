<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Closure;
use Override;
use UIAwesome\Html\Helper\Encode;
use Yii;
use yii\debug\helpers\Coerce;
use yii\debug\models\search\LogSearch;
use yii\debug\Panel;
use yii\debug\panels\dump\{DumpRow, DumpSnapshot};
use yii\helpers\VarDumper;
use yii\log\Logger;

use function array_key_exists;
use function count;

/**
 * Captures trace-level log messages emitted by `Yii::debug()` and renders them as dump cards.
 *
 * Filters the trace log by {@see $categories} (and skips categories owned by the Router panel) and stringifies each
 * captured value through {@see varDump()}, so the detail view can render the result without re-serializing.
 */
class DumpPanel extends Panel
{
    protected const string ICON = 'dump';
    protected const string NAME = 'Dump';

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

    private DumpSnapshot|null $snapshot = null;

    /**
     * Captures the trace-level messages allowed by {@see $categories}, excluding the categories owned by the Router
     * panel, and pre-renders each captured value through {@see varDump()}.
     */
    public function capture(): DumpSnapshot
    {
        $except = [];

        $routerPanel = $this->module->panels['router'] ?? null;

        if ($routerPanel instanceof RouterPanel) {
            $except = Coerce::stringList($routerPanel->getCategories());
        }

        $messages = $this->getLogMessages(Logger::LEVEL_TRACE, $this->categories, $except);

        foreach ($messages as &$message) {
            if (array_key_exists(0, $message) === false) {
                continue;
            }

            $message[0] = $this->varDump($message[0]);
        }

        return DumpSnapshot::capture($messages);
    }

    /**
     * Renders the detail view with the dump grid powered by the Log search model.
     */
    #[Override]
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
     * @return list<DumpRow> Captured dump rows in capture order.
     */
    public function getDumps(): array
    {
        return $this->snapshot?->entries() ?? [];
    }

    public function hasDumps(): bool
    {
        return $this->getDumps() !== [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->snapshot = DumpSnapshot::fromArray($payload, "$.panels.{$this->id}");
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
     * Returns the typed dump rows consumed by the dumps grid.
     *
     * @return list<DumpRow> Rows in capture order, suitable for {@see \yii\data\ArrayDataProvider}.
     */
    protected function getModels(): array
    {
        return $this->getDumps();
    }

    /**
     * Returns the toolbar item showing the number of dumped variables, or `[]` when none were captured.
     *
     * @return array<int, array<string, mixed>> Single-element list with the `info` chip, or `[]`.
     */
    #[Override]
    protected function getToolbarItems(): array
    {
        $dumps = $this->getDumps();

        if ($dumps === []) {
            return [];
        }

        return [
            [
                'status' => 'info',
                'title' => 'Number of dumped variables',
                'value' => count($dumps),
            ],
        ];
    }
}
