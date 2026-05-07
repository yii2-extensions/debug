<?php

declare(strict_types=1);

namespace yii\debug\panels;

use RuntimeException;
use Stringable;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\models\timeline\{Search, Svg};
use yii\debug\Panel;

use function is_array;
use function is_float;
use function is_int;
use function is_scalar;
use function is_string;

/**
 * Debugger panel that collects and displays timeline data.
 */
class TimelinePanel extends Panel
{
    /**
     * @var array<int, string> Color indicators item profile.
     *
     * - keys: percentages of time request
     * - values: hex color
     */
    private array $colors = [
        1 => '#8cc665',
        10 => '#44a340',
        20 => '#1e6823',
    ];
    /**
     * Request duration, milliseconds
     */
    private float $duration = 0.0;
    /**
     * End request, timestamp (obtained by microtime(true))
     */
    private float $end = 0.0;
    /**
     * Used memory in request
     */
    private int $memory = 0;
    /**
     * @var array<int, array<string, mixed>>|null Log messages extracted to array as models, to use with data provider.
     */
    private array|null $models = null;
    /**
     * Start request, timestamp (obtained by microtime(true))
     */
    private float $start = 0.0;
    /**
     * SVG factory instance for rendering timeline graph.
     */
    private Svg|null $svg = null;
    /**
     * @var array<string, mixed>
     */
    private array $svgOptions = [
        'class' => Svg::class,
    ];

    /**
     * Color indicators item profile,
     * key: percentages of time request, value: hex color
     *
     * @return array<int, string>
     */
    public function getColors(): array
    {
        return $this->colors;
    }

    public function getDetail(): string
    {
        $searchModel = new Search();
        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams(), $this);

        return Yii::$app->view->render(
            'panels/timeline/detail',
            [
                'dataProvider' => $dataProvider,
                'panel' => $this,
                'searchModel' => $searchModel,
            ],
        );
    }

    /**
     * Request duration, milliseconds.
     */
    public function getDuration(): float
    {
        return $this->duration;
    }

    /**
     * Memory peak in request, bytes. (obtained by memory_get_peak_usage()).
     */
    public function getMemory(): int
    {
        return $this->memory;
    }

    /**
     * Returns an array of models that represents logs of the current request.
     *
     * Can be used with data providers, such as {@see \yii\data\ArrayDataProvider}.
     *
     * @param bool $refresh if need to build models from log messages and refresh them.
     *
     * @return array<int, array<string, mixed>>
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

    public function getName(): string
    {
        return 'Timeline';
    }

    /**
     * Start request, timestamp (obtained by microtime(true))
     */
    public function getStart(): float
    {
        return $this->start;
    }

    /**
     * @throws InvalidConfigException
     * @since 2.0.8
     */
    public function getSvg(): Svg
    {
        $svg = $this->svg;

        if ($svg === null) {
            $class = self::stringValue($this->svgOptions['class'] ?? null) ?? Svg::class;

            if (!is_a($class, Svg::class, true)) {
                throw new InvalidConfigException('Timeline SVG class must extend ' . Svg::class . '.');
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
     * @return array<string, mixed>
     */
    public function getSvgOptions(): array
    {
        return $this->svgOptions;
    }

    public function getToolbarIcon(): string
    {
        return 'timeline';
    }

    /**
     * @throws InvalidConfigException if the profiling panel is not found in the module configuration.
     */
    public function init(): void
    {
        if ($this->module === null || !isset($this->module->panels['profiling'])) {
            throw new InvalidConfigException('Unable to determine the profiling panel');
        }

        parent::init();
    }

    public function load(mixed $data): void
    {
        if (!is_array($data)) {
            throw new RuntimeException(
                'Unable to load timeline data',
            );
        }

        $start = self::floatValue($data['start'] ?? null);

        if ($start === null || $start <= 0) {
            throw new RuntimeException(
                'Unable to determine request start time',
            );
        }

        $this->start = $start * 1000;

        $end = self::floatValue($data['end'] ?? null);

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

        $memory = self::intValue($data['memory'] ?? null);

        if ($memory === null || $memory <= 0) {
            throw new RuntimeException(
                'Unable to determine used memory in request',
            );
        }

        $this->memory = $memory;
    }

    /**
     * @return array{start: float, end: float, memory: int}
     */
    public function save(): array
    {
        return [
            'start' => self::floatValue($_SERVER['REQUEST_TIME_FLOAT'] ?? null) ?? YII_BEGIN_TIME,
            'end' => microtime(true),
            'memory' => memory_get_peak_usage(),
        ];
    }

    /**
     * Sets color indicators.
     * key: percentages of time request, value: hex color
     *
     * @param array<int|string, mixed> $colors
     */
    public function setColors(array $colors): void
    {
        $this->colors = self::normalizeColors($colors);
    }

    /**
     * @param array<string, mixed> $options
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
    private function getProfilingMessages(): array
    {
        $profilingPanel = $this->module?->panels['profiling'] ?? null;

        if (!$profilingPanel instanceof Panel || !is_array($profilingPanel->data)) {
            return [];
        }

        return self::normalizeMessages($profilingPanel->data['messages'] ?? []);
    }

    private function getProfilingTime(): float|null
    {
        $profilingPanel = $this->module?->panels['profiling'] ?? null;

        if (!$profilingPanel instanceof Panel || !is_array($profilingPanel->data)) {
            return null;
        }

        return self::floatValue($profilingPanel->data['time'] ?? null);
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
     * @param array<int|string, mixed> $colors
     *
     * @return array<int, string>
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
     * @param mixed $messages Raw profiling messages.
     *
     * @return array<int, array<int|string, mixed>>
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
     * @param mixed $timing Raw timing returned by Yii logger.
     *
     * @return array<string, mixed>|null
     */
    private static function normalizeTiming(mixed $timing): array|null
    {
        if (!is_array($timing)) {
            return null;
        }

        $timestamp = self::floatValue($timing['timestamp'] ?? null);
        $duration = self::floatValue($timing['duration'] ?? null);

        if ($timestamp === null || $duration === null) {
            return null;
        }

        return [
            'category' => self::stringValue($timing['category'] ?? null) ?? '',
            'duration' => $duration,
            'info' => self::stringValue($timing['info'] ?? null) ?? '',
            'level' => self::intValue($timing['level'] ?? null) ?? 0,
            'memory' => self::intValue($timing['memory'] ?? null) ?? 0,
            'memoryDiff' => self::intValue($timing['memoryDiff'] ?? null) ?? 0,
            'timestamp' => $timestamp,
            'trace' => self::normalizeTrace($timing['trace'] ?? []),
        ];
    }

    /**
     * @param mixed $trace Raw trace returned by Yii logger.
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
