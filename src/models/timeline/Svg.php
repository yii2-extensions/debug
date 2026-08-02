<?php

declare(strict_types=1);

namespace yii\debug\models\timeline;

use Stringable;
use UIAwesome\Html\Svg\{Defs, G, LinearGradient, Polygon, Polyline, Stop, Svg as SvgBuilder};
use yii\base\BaseObject;
use yii\debug\panels\{MemorySample, ProvidesMemorySamples, TimelinePanel};
use yii\helpers\StringHelper;

use function is_string;
use function usort;

/**
 * Renders the timeline panel's memory-usage graph as an inline SVG.
 *
 * Plots memory samples drawn from the log and profiling panels onto a polyline/polygon pair whose stroke and gradient
 * fill default to `currentColor`, so the surrounding CSS drives the chart color through per-stop opacities. The graph
 * is stringified through {@see __toString()} so the view can embed it inline.
 */
class Svg extends BaseObject implements Stringable
{
    /**
     * Gradient stops keyed by percentage threshold of total memory.
     *
     * A numeric value emits a `currentColor` stop with that `stop-opacity`, so the fill fades along the chart color
     * provided by CSS. A string value is emitted verbatim as the `stop-color` (fixed color ramps).
     *
     * @var array<int, float|int|string>
     */
    public array $gradient = [
        10 => 0.18,
        60 => 0.45,
        90 => 0.65,
        100 => 0.85,
    ];
    /**
     * Panel IDs whose log messages feed the graph.
     *
     * @var list<string>
     */
    public array $listenMessages = ['log', 'profiling'];
    /**
     * Stroke color for the rendered polyline.
     *
     * Defaults to `currentColor`, so the trace inherits the CSS `color` of the memory track.
     */
    public string $stroke = 'currentColor';
    /**
     * Canvas width in user units.
     */
    public int $x = 1920;
    /**
     * Canvas height in user units.
     */
    public int $y = 40;

    /**
     * Plotted points, each entry an `[x, y]` coordinate pair.
     *
     * @var list<array{0: float, 1: float}>
     */
    protected array $points = [];

    /**
     * @param TimelinePanel $panel Panel providing total memory, request start, and total duration.
     * @param array<string, mixed> $config Standard {@see BaseObject} configuration.
     */
    public function __construct(protected TimelinePanel $panel, array $config = [])
    {
        parent::__construct($config);

        $module = $panel->module;

        if ($module === null) {
            return;
        }

        foreach ($this->listenMessages as $panelId) {
            $sourcePanel = $module->panels[$panelId] ?? null;

            if ($sourcePanel instanceof ProvidesMemorySamples) {
                $this->addPoints($sourcePanel->getMemorySamples());
            }
        }
    }

    public function __toString(): string
    {
        if ($this->points === []) {
            return '';
        }

        return SvgBuilder::tag()
            ->height($this->y)
            ->html(
                Defs::tag()
                    ->html($this->buildGradient()),
                G::tag()->html(
                    Polygon::tag()
                        ->points($this->polygonPoints())
                        ->fill('url(#yii-debug-tl-memory-gradient)'),
                    Polyline::tag()
                        ->points($this->polylinePoints())
                        ->fill('none')
                        ->stroke($this->stroke)
                        ->strokeWidth('1.5'),
                ),
            )
            ->preserveAspectRatio('none')
            ->viewBox("0 0 {$this->x} {$this->y}")
            ->width($this->x)
            ->xmlns('http://www.w3.org/2000/svg')
            ->render();
    }

    /**
     * Returns whether at least one point has been plotted.
     */
    public function hasPoints(): bool
    {
        return $this->points !== [];
    }

    /**
     * Appends plotted points sourced from a panel's memory samples.
     *
     * @param list<MemorySample> $samples Samples to plot.
     *
     * @return int Number of samples plotted.
     */
    protected function addPoints(array $samples): int
    {
        $hasPoints = $this->hasPoints();

        $panelMemory = $this->panel->getMemory();

        if ($panelMemory <= 0 || $this->x <= 0) {
            return 0;
        }

        $memory = $panelMemory / 100;
        $yOne = $this->y / 100;
        $xOne = $this->panel->getDuration() / $this->x;

        if ($xOne <= 0) {
            return 0;
        }

        $i = 0;

        foreach ($samples as $sample) {
            ++$i;

            $this->points[] = [
                ($sample->time - $this->panel->getStart()) / $xOne,
                $this->y - ($sample->memory / $memory * $yOne),
            ];
        }

        if ($hasPoints && $i > 0) {
            usort($this->points, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        }

        return $i;
    }

    /**
     * Builds the gradient definition wrapped in a `<linearGradient id="yii-debug-tl-memory-gradient">` element.
     *
     * Numeric {@see $gradient} values become `currentColor` stops with the value as `stop-opacity`; string values are
     * emitted verbatim as fixed `stop-color` entries.
     */
    private function buildGradient(): LinearGradient
    {
        $stops = [];

        foreach ($this->gradient as $percent => $stop) {
            $tag = Stop::tag()->offset(StringHelper::normalizeNumber($percent) . '%');

            $stops[] = is_string($stop)
                ? $tag->stopColor($stop)
                : $tag->stopColor('currentColor')->stopOpacity(StringHelper::normalizeNumber($stop));
        }

        return LinearGradient::tag()
            ->id('yii-debug-tl-memory-gradient')
            ->x1(0)
            ->x2(0)
            ->y1(1)
            ->y2(0)
            ->html(...$stops);
    }

    /**
     * Returns the value for the polygon's `points` attribute.
     */
    private function polygonPoints(): string
    {
        $y = (float) $this->y;
        $str = "0 {$this->y} ";

        foreach ($this->points as $point) {
            [$x, $y] = $point;
            $str .= "{$x} {$y} ";
        }

        $str .= ($this->x - 0.001) . " {$y} {$this->x} {$this->y}";

        return StringHelper::normalizeNumber($str);
    }

    /**
     * Returns the value for the polyline's `points` attribute.
     */
    private function polylinePoints(): string
    {
        $y = (float) $this->y;

        $str = "0 {$this->y} ";

        foreach ($this->points as $point) {
            [$x, $y] = $point;
            $str .= "{$x} {$y} ";
        }

        $str .= "{$this->x} {$y}";

        return StringHelper::normalizeNumber($str);
    }
}
