<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use Closure;
use PHPForge\Debug\Panel\Dump\DumpSnapshot;
use UIAwesome\Html\Helper\Encode;
use yii\helpers\VarDumper;
use yii\log\Logger;

/**
 * Captures trace-level log messages emitted by `Yii::debug()` for the Dump panel.
 *
 * Filters the trace log by {@see $categories} (and skips categories owned by the Router collector) and stringifies
 * each captured value through {@see varDump()}, so the detail view can render the result without re-serializing.
 *
 * Usage example:
 *
 * ```php
 * $snapshot = (new \yii\debug\collectors\DumpCollector())->capture();
 * ```
 */
class DumpCollector extends Collector
{
    /**
     * @var list<string> Message categories to capture; an empty list captures every category.
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
     * Captures the trace-level messages allowed by {@see $categories}, excluding the categories owned by the Router
     * collector, and pre-renders each captured value through {@see varDump()}.
     *
     * @return DumpSnapshot|null Captured dump payload; `null` when the collector never started.
     */
    public function capture(): DumpSnapshot|null
    {
        if (!$this->isStarted()) {
            return null;
        }

        $except = [];

        $routerCollector = $this->module?->getCollectorCoordinator()->collector('router');

        if ($routerCollector instanceof RouterCollector) {
            $except = $routerCollector->getCategories();
        }

        $messages = $this->getLogMessages(
            Logger::LEVEL_TRACE,
            $this->categories,
            $except,
            $this->varDump(...),
        );

        return DumpSnapshot::capture($messages);
    }

    /**
     * Returns the stable ID pairing this collector with the Dump panel.
     *
     * Usage example:
     *
     * ```php
     * $id = (new \yii\debug\collectors\DumpCollector())->id();
     * ```
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'dump';
    }

    /**
     * Renders a captured value as a display string.
     *
     * The built-in highlighter emits safe markup, so highlighted output is passed through unchanged. Plain output
     * and custom callback output are HTML-escaped explicitly.
     *
     * Usage example:
     *
     * ```php
     * $html = $collector->varDump(['answer' => 42]);
     * ```
     */
    public function varDump(mixed $var): string
    {
        if ($this->varDumpCallback !== null) {
            return Encode::content(($this->varDumpCallback)($var, $this));
        }

        $message = VarDumper::dumpAsString($var, $this->depth, $this->highlight);

        if (!$this->highlight) {
            $message = Encode::content($message);
        }

        return $message;
    }
}
