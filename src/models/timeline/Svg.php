<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\models\timeline;

use RuntimeException;
use UIAwesome\Html\Svg\{Defs, G, LinearGradient, Polygon, Polyline, Stop, Svg as SvgBuilder};
use yii\base\BaseObject;
use yii\debug\panels\TimelinePanel;
use yii\helpers\StringHelper;

use function is_array;
use function is_numeric;

/**
 * Svg renders the memory-usage graph as an inline SVG.
 */
class Svg extends BaseObject
{
    /**
     * Color stops for the gradient fill, keyed by percentage threshold.
     *
     * @var array<int, string>
     */
    public array $gradient = [
        10 => '#d6e685',
        60 => '#8cc665',
        90 => '#44a340',
        100 => '#1e6823',
    ];
    /**
     * Identifiers of the panels whose log messages feed the graph.
     *
     * @var list<string>
     */
    public array $listenMessages = ['log', 'profiling'];
    /**
     * Stroke color for the polyline.
     */
    public string $stroke = '#1e6823';
    /**
     * Maximum X coordinate of the canvas.
     */
    public int $x = 1920;
    /**
     * Maximum Y coordinate of the canvas.
     */
    public int $y = 40;

    protected TimelinePanel|null $panel = null;

    /**
     * Plotted points; each entry is a `[x, y]` coordinate pair.
     *
     * @var list<array{0: float, 1: float}>
     */
    protected array $points = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(TimelinePanel $panel, array $config = [])
    {
        parent::__construct($config);

        $this->panel = $panel;

        $module = $panel->module;

        if ($module === null) {
            return;
        }

        foreach ($this->listenMessages as $panelId) {
            $sourcePanel = $module->panels[$panelId] ?? null;

            if ($sourcePanel === null) {
                continue;
            }

            $data = $sourcePanel->data;

            if (!is_array($data) || !isset($data['messages']) || !is_array($data['messages'])) {
                continue;
            }

            $this->addPoints($data['messages']);
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
                        ->fill('url(#gradient)'),
                    Polyline::tag()
                        ->points($this->polylinePoints())
                        ->fill('none')
                        ->stroke($this->stroke)
                        ->strokeWidth(1),
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
     * Appends plotted points sourced from a panel's log messages.
     *
     * @param array<array-key, mixed> $messages Log messages with the structure documented in {@see Logger::messages}.
     *
     * @return int Number of points added.
     */
    protected function addPoints(array $messages): int
    {
        $hasPoints = $this->hasPoints();
        $panelMemory = $this->panel()->getMemory();

        if ($panelMemory <= 0 || $this->x <= 0) {
            return 0;
        }

        $memory = $panelMemory / 100;
        $yOne = $this->y / 100;
        $xOne = $this->panel()->getDuration() / $this->x;

        if ($xOne <= 0) {
            return 0;
        }

        $i = 0;

        foreach ($messages as $message) {
            if (
                !is_array($message)
                || !isset($message[3], $message[5])
                || !is_numeric($message[3])
                || !is_numeric($message[5])
            ) {
                break;
            }

            ++$i;

            $this->points[] = [
                ((float) $message[3] * 1000 - $this->panel()->getStart()) / $xOne,
                $this->y - ((float) $message[5] / $memory * $yOne),
            ];
        }

        if ($hasPoints && $i > 0) {
            usort($this->points, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        }

        return $i;
    }

    /**
     * Builds the gradient definition wrapped in a `<linearGradient id="gradient">` element.
     */
    private function buildGradient(): LinearGradient
    {
        $stops = [];

        foreach ($this->gradient as $percent => $color) {
            $stops[] = Stop::tag()
                ->offset(StringHelper::normalizeNumber($percent) . '%')
                ->stopColor($color);
        }

        return LinearGradient::tag()
            ->id('gradient')
            ->x1(0)
            ->x2(0)
            ->y1(1)
            ->y2(0)
            ->html(...$stops);
    }

    /**
     * Returns the bound {@see TimelinePanel}, asserting that it has been set by the constructor.
     */
    private function panel(): TimelinePanel
    {
        if ($this->panel === null) {
            throw new RuntimeException('TimelinePanel has not been set on the SVG renderer.');
        }

        return $this->panel;
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
