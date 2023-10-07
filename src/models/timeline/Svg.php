<?php

declare(strict_types=1);

namespace yii\debug\models\timeline;

use yii\base\BaseObject;
use yii\debug\panels\TimelinePanel;
use yii\helpers\StringHelper;

use function usort;
use function strtr;

/**
 * Svg is used to draw a graph using SVG.
 */
class Svg extends BaseObject
{
    /**
     * @var int Max X coordinate.
     */
    public int $x = 1920;
    /**
     * @var int Max Y coordinate.
     */
    public int $y = 40;
    /**
     * @var string Stroke color.
     */
    public string $stroke = '#1e6823';
    /**
     * @var array Listen messages panels.
     */
    public array $listenMessages = ['log', 'profiling'];
    /**
     * @var array Color indicators svg graph.
     */
    public array $gradient = [
        10 => '#d6e685',
        60 => '#8cc665',
        90 => '#44a340',
        100 => '#1e6823',
    ];
    /**
     * @var string Svg template.
     */
    public string $template = '<svg xmlns="http://www.w3.org/2000/svg" width="{x}" height="{y}" viewBox="0 0 {x} {y}" preserveAspectRatio="none"><defs>{linearGradient}</defs><g><polygon points="{polygon}" fill="url(#gradient)"/><polyline points="{polyline}" fill="none" stroke="{stroke}" stroke-width="1"/></g></svg>';

    /**
     * ```php
     * [
     *  [x, y]
     * ]
     * ```
     *
     * @var array Each point is defined by an X and a Y coordinate.
     */
    protected array $points = [];
    protected array|TimelinePanel $panel;

    /**
     * {@inheritdoc}
     */
    public function __construct(TimelinePanel $panel, $config = [])
    {
        parent::__construct($config);

        $this->panel = $panel;

        foreach ($this->listenMessages as $panel) {
            if (isset($this->panel->module->panels[$panel]->data['messages'])) {
                $this->addPoints($this->panel->module->panels[$panel]->data['messages']);
            }
        }
    }

    public function __toString()
    {
        if ($this->points === []) {
            return '';
        }

        return strtr($this->template, [
            '{x}' => StringHelper::normalizeNumber($this->x),
            '{y}' => StringHelper::normalizeNumber($this->y),
            '{stroke}' => $this->stroke,
            '{polygon}' => $this->polygon(),
            '{polyline}' => $this->polyline(),
            '{linearGradient}' => $this->linearGradient(),
        ]);
    }

    public function hasPoints(): bool
    {
        return $this->points !== [];
    }

    /**
     * Add points to the graph.
     *
     * @param array $messages log messages. See [[Logger::messages]] for the structure.
     *
     * @return int added points.
     */
    protected function addPoints(array $messages): int
    {
        $hasPoints = $this->hasPoints();

        $memory = $this->panel->getMemory() / 100; // 1 percent memory

        $yOne = $this->y / 100; // 1 percent Y coordinate

        $xOne = $this->panel->duration / $this->x; // 1 percent X coordinate

        $i = 0;

        foreach ($messages as $message) {
            if (empty($message[5])) {
                break;
            }

            ++$i;
            $this->points[] = [
                ($message[3] * 1000 - $this->panel->start) / $xOne,
                $this->y - ($message[5] / $memory * $yOne),
            ];
        }

        if ($hasPoints && $i) {
            usort($this->points, static function ($a, $b) {
                return ($a[0] < $b[0]) ? -1 : 1;
            });
        }

        return $i;
    }

    /**
     * @return string Points attribute for a polygon path.
     */
    protected function polygon(): string
    {
        $str = "0 $this->y ";
        $y = 0;

        foreach ($this->points as $point) {
            [$x, $y] = $point;
            $str .= "$x $y ";
        }

        $str .= $this->x - 0.001 . " $y $this->x $this->y";

        return StringHelper::normalizeNumber($str);
    }

    /**
     * @return string Points attribute for a polyline path.
     */
    protected function polyline(): string
    {
        $str = "0 $this->y ";
        $y = 0;

        foreach ($this->points as $point) {
            [$x, $y] = $point;
            $str .= "$x $y ";
        }

        $str .= "$this->x $y";

        return StringHelper::normalizeNumber($str);
    }

    protected function linearGradient(): string
    {
        $gradient = '<linearGradient id="gradient" x1="0" x2="0" y1="1" y2="0">';

        foreach ($this->gradient as $percent => $color) {
            $gradient .= '<stop offset="' . StringHelper::normalizeNumber($percent) . '%" stop-color="' . $color . '"></stop>';
        }

        return $gradient . '</linearGradient>';
    }
}
