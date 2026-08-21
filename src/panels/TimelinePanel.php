<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Panel\Profile\ProfileRow;
use PHPForge\Debug\Panel\Timeline\TimelineSnapshot;
use RuntimeException;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\exception\Message;
use yii\debug\models\search\TimelineSearch;
use yii\debug\models\timeline\Svg;
use yii\debug\Panel;

/**
 * Renders the request's profile spans as a horizontal timeline chart.
 *
 * Joins the request start/end captured by {@see \yii\debug\collectors\TimelineCollector} with the profile messages
 * from {@see ProfilingPanel} to build the per-span timeline and exposes an inline SVG memory-usage line through
 * {@see getSvg()}.
 */
class TimelinePanel extends Panel
{
    protected const string ICON = 'timeline';
    protected const string NAME = 'Timeline';

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
     * Profiling panel resolved by {@see init()}, providing the spans and the authoritative request duration.
     */
    private ProfilingPanel|null $profilingPanel = null;
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
     * Renders the detail view with the timeline chart and the filter form.
     */
    #[Override]
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
     * Returns the profile blocks the chart renders, resolved once by the {@see ProfilingPanel} at capture time.
     *
     * @return list<ProfileRow> Blocks in capture order.
     */
    public function getModels(): array
    {
        return $this->profilingPanel?->getModels() ?? [];
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
            $svgClass = Svg::class;

            if (!is_a($class, Svg::class, true)) {
                throw new InvalidConfigException(
                    Message::TIMELINE_SVG_CLASS_INVALID->getMessage($svgClass),
                );
            }

            $config = $this->svgOptions;

            unset($config['class']);

            $object = Yii::$container->get($class, [$this], $config);

            if (!$object instanceof Svg) {
                throw new InvalidConfigException(
                    Message::TIMELINE_SVG_FACTORY_INVALID->getMessage($svgClass),
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
     * Hydrates the panel from the saved snapshot: resolves the request start/end, computes the duration (preferring
     * the Profiling panel's authoritative time when available), and records the peak memory.
     *
     * @throws RuntimeException When any of `start`, `end`, `memory`, or the derived `duration` is missing or invalid.
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $snapshot = TimelineSnapshot::fromArray($payload, "$.panels.{$this->id}");

        $start = $snapshot->start;

        if ($start <= 0) {
            throw new RuntimeException(
                Message::REQUEST_START_TIME_UNAVAILABLE->getMessage(),
            );
        }

        $this->start = $start * 1000;

        $end = $snapshot->end;

        if ($end <= 0) {
            throw new RuntimeException(
                Message::REQUEST_END_TIME_UNAVAILABLE->getMessage(),
            );
        }

        $this->end = $end * 1000;

        $profilingTime = $this->profilingPanel?->getProcessingTime();

        if ($profilingTime !== null) {
            $this->duration = $profilingTime * 1000;
        } else {
            $this->duration = $this->end - $this->start;
        }

        if ($this->duration <= 0) {
            throw new RuntimeException(
                Message::TIMELINE_DURATION_ZERO->getMessage(),
            );
        }

        $memory = $snapshot->memory;

        if ($memory <= 0) {
            throw new RuntimeException(
                Message::REQUEST_MEMORY_UNAVAILABLE->getMessage(),
            );
        }

        $this->memory = $memory;
    }

    /**
     * Resolves the {@see ProfilingPanel} the timeline reads its spans and duration from, before delegating to the
     * parent initializer.
     *
     * @throws InvalidConfigException When the module registers no `profiling` panel, or registers one that is not a
     * {@see ProfilingPanel}.
     */
    public function init(): void
    {
        $profilingPanel = $this->module?->panels['profiling'] ?? null;

        if (!$profilingPanel instanceof ProfilingPanel) {
            throw new InvalidConfigException(
                Message::PROFILING_PANEL_UNAVAILABLE->getMessage(),
            );
        }

        $this->profilingPanel = $profilingPanel;

        parent::init();
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
}
