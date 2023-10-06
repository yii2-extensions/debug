<?php

declare(strict_types=1);

namespace yii\debug\panels;

use RuntimeException;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\models\timeline\Search;
use yii\debug\models\timeline\Svg;
use yii\debug\Panel;

use function array_merge;
use function krsort;
use function memory_get_peak_usage;
use function microtime;

/**
 * Debugger panel that collects and displays timeline data.
 */
class TimelinePanel extends Panel
{
    /**
     * @var array Color indicators item profile.
     *
     * - keys: percentages of time request
     * - values: hex color
     */
    private array $_colors = [
        20 => '#1e6823',
        10 => '#44a340',
        1 => '#8cc665',
    ];
    /**
     * @var array log messages extracted to array as models, to use with data provider.
     */
    private array $_models;
    /**
     * @var float Start request, timestamp (obtained by microtime(true))
     */
    private float $_start;
    /**
     * @var float End request, timestamp (obtained by microtime(true))
     */
    private float $_end;
    /**
     * @var float Request duration, milliseconds
     */
    private float $_duration;
    /**
     * @var Svg|null
     */
    private Svg|null $_svg;
    /**
     * @var array
     */
    private array $_svgOptions = [
        'class' => Svg::class,
    ];
    /**
     * @var int Used memory in request
     */
    private int $_memory;

    /**
     * {@inheritdoc}
     *
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        if (!isset($this->module->panels['profiling'])) {
            throw new InvalidConfigException('Unable to determine the profiling panel');
        }

        parent::init();
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Timeline';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetail(): string
    {
        $searchModel = new Search();
        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams(), $this);

        return Yii::$app->view->render('panels/timeline/detail', [
            'panel' => $this,
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function load(mixed $data): void
    {
        if (empty($data['start'])) {
            throw new RuntimeException('Unable to determine request start time');
        }

        $this->_start = $data['start'] * 1000;

        if (empty($data['end'])) {
            throw new RuntimeException('Unable to determine request end time');
        }

        $this->_end = $data['end'] * 1000;

        if (isset($this->module->panels['profiling']->data['time'])) {
            $this->_duration = $this->module->panels['profiling']->data['time'] * 1000;
        } else {
            $this->_duration = $this->_end - $this->_start;
        }

        if ($this->_duration <= 0) {
            throw new RuntimeException('Duration cannot be zero');
        }

        if (empty($data['memory'])) {
            throw new RuntimeException('Unable to determine used memory in request');
        }

        $this->_memory = $data['memory'];
    }

    /**
     * {@inheritdoc}
     */
    public function save(): mixed
    {
        return [
            'start' => YII_BEGIN_TIME,
            'end' => microtime(true),
            'memory' => memory_get_peak_usage(),
        ];
    }

    /**
     * Sets color indicators.
     * key: percentages of time request, value: hex color
     *
     * @param array $colors
     */
    public function setColors(array $colors): void
    {
        krsort($colors);
        $this->_colors = $colors;
    }

    /**
     * Color indicators item profile,
     * key: percentages of time request, value: hex color
     *
     * @return array
     */
    public function getColors(): array
    {
        return $this->_colors;
    }

    public function setSvgOptions(array $options): void
    {
        if ($this->_svg !== null) {
            $this->_svg = null;
        }
        $this->_svgOptions = array_merge($this->_svgOptions, $options);
    }

    public function getSvgOptions(): array
    {
        return $this->_svgOptions;
    }

    /**
     * Start request, timestamp (obtained by microtime(true))
     *
     * @return float
     */
    public function getStart(): float
    {
        return $this->_start;
    }

    /**
     * Request duration, milliseconds
     *
     * @return float
     */
    public function getDuration(): float
    {
        return $this->_duration;
    }

    /**
     * Memory peak in request, bytes. (Obtained by memory_get_peak_usage())
     */
    public function getMemory(): int
    {
        return $this->_memory;
    }

    /**
     * @throws InvalidConfigException
     */
    public function getSvg(): Svg
    {
        if ($this->_svg === null) {
            $this->_svg = Yii::createObject($this->_svgOptions, [$this]);
        }

        return $this->_svg;
    }

    /**
     * Returns an array of models that represents logs of the current request.
     * Can be used with data providers, such as \yii\data\ArrayDataProvider.
     *
     * @param bool $refresh if you need to build models from log messages and refresh them.
     *
     * @return array models
     */
    protected function getModels(bool $refresh = false): array
    {
        if ($refresh) {
            $this->_models = [];

            if (isset($this->module->panels['profiling']->data['messages'])) {
                $this->_models = Yii::getLogger()->calculateTimings($this->module->panels['profiling']->data['messages']);
            }
        }

        return $this->_models;
    }
}
