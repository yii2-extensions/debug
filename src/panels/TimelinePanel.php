<?php

declare(strict_types=1);

namespace yii\debug\panels;

use RuntimeException;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\helpers\Coerce;
use yii\debug\models\search\TimelineSearch;
use yii\debug\models\timeline\Svg;
use yii\debug\Panel;

use function is_array;
use function is_string;

/**
 * Captures the request's profile spans and renders them as a horizontal timeline chart.
 *
 * Joins the request start/end captured at `save()` time with the profile messages from {@see ProfilingPanel} to build
 * the per-span timeline, color-codes each bar based on its share of the total duration, and exposes an inline SVG
 * memory-usage line through {@see getSvg()}.
 *
 * @extends Panel<array{start?: mixed, end?: mixed, memory?: mixed}>
 */
class TimelinePanel extends Panel
{
    /**
     * @var array<int, string> Color indicators keyed by percentage threshold of total request time, valued by hex
     * color; bars whose width is greater than or equal to the threshold render in that color.
     */
    private array $colors = [
        1 => '#8cc665',
        10 => '#44a340',
        20 => '#1e6823',
    ];
    /**
     * Request duration in milliseconds (resolved from the Profiling panel when available, otherwise `end - start`).
     */
    private float $duration = 0.0;
    /**
     * Request end timestamp, in milliseconds since the Unix epoch.
     */
    private float $end = 0.0;
    /**
     * Peak memory usage in bytes (captured via {@see memory_get_peak_usage()}).
     */
    private int $memory = 0;
    /**
     * @var array<int, array<string, mixed>>|null Cached typed span rows consumed by the timeline chart.
     */
    private array|null $models = null;
    /**
     * Request start timestamp, in milliseconds since the Unix epoch.
     */
    private float $start = 0.0;
    /**
     * Memoized SVG renderer, instantiated lazily by {@see getSvg()}.
     */
    private Svg|null $svg = null;
    /**
     * @var array<string, mixed> Constructor configuration merged into the SVG renderer at {@see getSvg()} time.
     */
    private array $svgOptions = [
        'class' => Svg::class,
    ];

    /**
     * Returns the color indicators keyed by percentage threshold and valued by hex color.
     *
     * @return array<int, string> Color map in `percent => #hex` order.
     */
    public function getColors(): array
    {
        return $this->colors;
    }

    /**
     * Renders the detail view with the timeline chart and the filter form.
     */
    public function getDetail(): string
    {
        $searchModel = new TimelineSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams(), $this);

        return Yii::$app->view->render(
            'panels/timeline/detail',
            [
                'dataProvider' => $dataProvider,
                'panel' => $this,
                'searchModel' => $searchModel,
            ],
            $this,
        );
    }

    /**
     * Returns the total request duration in milliseconds.
     */
    public function getDuration(): float
    {
        return $this->duration;
    }

    /**
     * Returns the peak memory usage in bytes.
     */
    public function getMemory(): int
    {
        return $this->memory;
    }

    /**
     * Builds and caches the typed span rows consumed by the timeline chart.
     *
     * Suitable for {@see \yii\data\ArrayDataProvider}.
     *
     * @param bool $refresh `true` to rebuild the cache from the profile messages.
     *
     * @return array<int, array<string, mixed>> Span rows in capture order.
     */
    public function getModels(bool $refresh = false): array
    {
        if ($this->models === null || $refresh) {
            $this->models = [];

            $rawTimings = Yii::getLogger()->calculateTimings($this->getProfilingMessages());

            foreach ($rawTimings as $rawTiming) {
                $timing = self::normalizeTiming($rawTiming);

                if ($timing !== null) {
                    $this->models[] = $timing;
                }
            }
        }

        return $this->models;
    }

    /**
     * Returns the panel display name.
     */
    public function getName(): string
    {
        return 'Timeline';
    }

    /**
     * Returns the request start timestamp in milliseconds since the Unix epoch.
     */
    public function getStart(): float
    {
        return $this->start;
    }

    /**
     * Returns the memoized SVG renderer, instantiating it lazily on first call.
     *
     * @throws InvalidConfigException When `svgOptions['class']` does not extend {@see Svg}, or the container produces
     * something else.
     */
    public function getSvg(): Svg
    {
        $svg = $this->svg;

        if ($svg === null) {
            $class = Coerce::stringOrNull($this->svgOptions['class'] ?? null) ?? Svg::class;

            if (!is_a($class, Svg::class, true)) {
                throw new InvalidConfigException(
                    'Timeline SVG class must extend ' . Svg::class . '.',
                );
            }

            $config = $this->svgOptions;

            unset($config['class']);

            $object = Yii::$container->get($class, [$this], $config);

            if (!$object instanceof Svg) {
                throw new InvalidConfigException(
                    'Timeline SVG factory must create ' . Svg::class . '.',
                );
            }

            $svg = $object;

            $this->svg = $svg;
        }

        return $svg;
    }

    /**
     * Returns the constructor configuration that will be applied to the SVG renderer.
     *
     * @return array<string, mixed> Configuration carrying the `class` key plus any merged options.
     */
    public function getSvgOptions(): array
    {
        return $this->svgOptions;
    }

    /**
     * Returns the toolbar icon name.
     */
    public function getToolbarIcon(): string
    {
        return 'timeline';
    }

    /**
     * Verifies that the {@see ProfilingPanel} is registered before delegating to the parent initializer.
     *
     * @throws InvalidConfigException When the profiling panel is not registered on the module.
     */
    public function init(): void
    {
        if ($this->module === null || !isset($this->module->panels['profiling'])) {
            throw new InvalidConfigException(
                'Unable to determine the profiling panel',
            );
        }

        parent::init();
    }

    /**
     * Hydrates the panel from the saved snapshot: resolves the request start/end, computes the duration (preferring
     * the Profiling panel's authoritative time when available), and records the peak memory.
     *
     * The parameter is intentionally widened to `mixed` (vs. the parent's typed `TData`) because the runtime feed comes
     * straight out of {@see \yii\debug\LogTarget::loadTagToPanels()}, where the value is whatever `@unserialize()`
     * returned including `false` on a corrupt snapshot.
     *
     * @param mixed $data Raw payload returned by `@unserialize()` of a captured request snapshot.
     *
     * @throws RuntimeException When any of `start`, `end`, `memory`, or the derived `duration` is missing or invalid.
     */
    public function load(mixed $data): void
    {
        if (!is_array($data)) {
            throw new RuntimeException(
                'Unable to load timeline data',
            );
        }

        $start = Coerce::floatOrNull($data['start'] ?? null);

        if ($start === null || $start <= 0) {
            throw new RuntimeException(
                'Unable to determine request start time',
            );
        }

        $this->start = $start * 1000;

        $end = Coerce::floatOrNull($data['end'] ?? null);

        if ($end === null || $end <= 0) {
            throw new RuntimeException(
                'Unable to determine request end time',
            );
        }

        $this->end = $end * 1000;

        $profilingTime = $this->getProfilingTime();

        if ($profilingTime !== null) {
            $this->duration = $profilingTime * 1000;
        } else {
            $this->duration = $this->end - $this->start;
        }

        if ($this->duration <= 0) {
            throw new RuntimeException(
                'Duration cannot be zero',
            );
        }

        $memory = Coerce::intOrNull($data['memory'] ?? null);

        if ($memory === null || $memory <= 0) {
            throw new RuntimeException(
                'Unable to determine used memory in request',
            );
        }

        $this->memory = $memory;
    }

    /**
     * Snapshots the request start (`$_SERVER['REQUEST_TIME_FLOAT']` with `microtime(true)` fallback), end, and peak
     * memory.
     *
     * @return array{start: float, end: float, memory: int} Captured payload consumed by {@see load()} on read-back.
     */
    public function save(): array
    {
        return [
            'start' => Coerce::floatOrNull($_SERVER['REQUEST_TIME_FLOAT'] ?? null) ?? microtime(true),
            'end' => microtime(true),
            'memory' => memory_get_peak_usage(),
        ];
    }

    /**
     * Sets the color indicators map (`percent => #hex`), sorted in descending percentage order on assignment so
     * {@see getColors()} returns the most specific threshold first.
     *
     * @param array<int|string, mixed> $colors Color map to apply; non-string values are dropped.
     */
    public function setColors(array $colors): void
    {
        $this->colors = self::normalizeColors($colors);
    }

    /**
     * Merges the given options into {@see $svgOptions} and resets the memoized renderer, so the next {@see getSvg()}
     * call rebuilds it with the updated configuration.
     *
     * @param array<string, mixed> $options Options to merge.
     */
    public function setSvgOptions(array $options): void
    {
        if ($this->svg !== null) {
            $this->svg = null;
        }

        $this->svgOptions = [
            ...$this->svgOptions,
            ...$options,
        ];
    }

    /**
     * Returns the saved profile messages from the {@see ProfilingPanel}, used to build the timeline spans.
     *
     * @return array<int, array<int|string, mixed>> Profile messages in capture order, or `[]` when the panel is not
     * registered or has no captured data.
     */
    private function getProfilingMessages(): array
    {
        $profilingPanel = $this->module?->panels['profiling'] ?? null;

        if (!$profilingPanel instanceof Panel || !is_array($profilingPanel->data)) {
            return [];
        }

        return self::normalizeMessages($profilingPanel->data['messages'] ?? []);
    }

    /**
     * Returns the authoritative request duration captured by the {@see ProfilingPanel}, in seconds, or `null` when
     * unavailable.
     */
    private function getProfilingTime(): float|null
    {
        $profilingPanel = $this->module?->panels['profiling'] ?? null;

        if (!$profilingPanel instanceof Panel || !is_array($profilingPanel->data)) {
            return null;
        }

        return Coerce::floatOrNull($profilingPanel->data['time'] ?? null);
    }

    /**
     * Narrows the input map to `int => string` entries and sorts by descending percentage so the most specific
     * threshold sits first.
     *
     * @param array<int|string, mixed> $colors Raw color map.
     *
     * @return array<int, string> Sorted color map keyed by percentage threshold.
     */
    private static function normalizeColors(array $colors): array
    {
        $normalized = [];

        foreach ($colors as $percent => $color) {
            if (is_string($color)) {
                $normalized[(int) $percent] = $color;
            }
        }

        krsort($normalized);

        return $normalized;
    }

    /**
     * Filters the raw profile messages to keep only array entries.
     *
     * @param mixed $messages Raw profiling messages.
     *
     * @return array<int, array<int|string, mixed>> Reindexed list of message arrays.
     */
    private static function normalizeMessages(mixed $messages): array
    {
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
     * Narrows a raw timing returned by the Yii logger into the typed span-row shape, returning `null` when either
     * `timestamp` or `duration` is missing or non-numeric.
     *
     * @param mixed $timing Raw timing returned by Yii logger.
     *
     * @return array<string, mixed>|null Normalized span row, or `null` when the input was incomplete.
     */
    private static function normalizeTiming(mixed $timing): array|null
    {
        if (!is_array($timing)) {
            return null;
        }

        $timestamp = Coerce::floatOrNull($timing['timestamp'] ?? null);
        $duration = Coerce::floatOrNull($timing['duration'] ?? null);

        if ($timestamp === null || $duration === null) {
            return null;
        }

        return [
            'category' => Coerce::stringOrNull($timing['category'] ?? null) ?? '',
            'duration' => $duration,
            'info' => Coerce::stringOrNull($timing['info'] ?? null) ?? '',
            'level' => Coerce::intOrNull($timing['level'] ?? null) ?? 0,
            'memory' => Coerce::intOrNull($timing['memory'] ?? null) ?? 0,
            'memoryDiff' => Coerce::intOrNull($timing['memoryDiff'] ?? null) ?? 0,
            'timestamp' => $timestamp,
            'trace' => Coerce::traceFrames($timing['trace'] ?? []),
        ];
    }
}
