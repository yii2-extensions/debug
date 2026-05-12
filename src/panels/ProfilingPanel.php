<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Yii;
use yii\debug\helpers\{Coerce, Format};
use yii\debug\models\search\Profile;
use yii\debug\Panel;
use yii\helpers\Url;
use yii\log\Logger;

use function is_array;

/**
 * Debugger panel that collects and displays performance profiling info.
 */
class ProfilingPanel extends Panel
{
    /**
     * @var array<int, array{
     *   duration: float,
     *   category: string,
     *   info: string,
     *   level: int,
     *   timestamp: float,
     *   seq: int
     * }>|null Current request profile timings
     */
    private array|null $models = null;

    public function getDetail(): string
    {
        $profileData = $this->getProfileData();

        $searchModel = new Profile();

        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams(), $this->getModels());

        $module = $this->module;
        $timelineUrl = $module === null
            ? '#'
            : Url::to(
                ['/' . $module->getUniqueId() . '/default/view', 'panel' => 'timeline', 'tag' => $this->tag],
            );

        return Yii::$app->view->render(
            'panels/profile/detail',
            [
                'dataProvider' => $dataProvider,
                'memory' => Format::bytesToMb($profileData['memory'], 3),
                'panel' => $this,
                'searchModel' => $searchModel,
                'time' => number_format($profileData['time'] * 1000) . ' ms',
                'timelineUrl' => $timelineUrl,
            ],
        );
    }

    public function getName(): string
    {
        return 'Profiling';
    }

    public function getSummary(): string
    {
        $profileData = $this->getProfileData();

        return Yii::$app->view->render(
            'panels/profile/summary',
            [
                'memory' => Format::bytesToMb($profileData['memory'], 3),
                'panel' => $this,
                'time' => number_format($profileData['time'] * 1000) . ' ms',
            ],
        );
    }

    /**
     * {@inheritdoc}
     *
     * Hides the "Profiling" panel-title from the toolbar — the gauge icon plus the time/memory metrics are
     * self-explanatory.
     *
     * @return array<string, mixed>
     */
    public function getToolbarData(): array
    {
        $data = parent::getToolbarData();

        if ($data !== [] && !$this->hasError()) {
            $data['title'] = '';
        }

        return $data;
    }

    public function getToolbarIcon(): string
    {
        return 'profiling';
    }

    /**
     * @return array{memory: int, time: float, messages: array<int, array<int|string, mixed>>}
     */
    public function save(): array
    {
        $messages = $this->getLogMessages(Logger::LEVEL_PROFILE);

        $requestStart = Coerce::floatOrNull($_SERVER['REQUEST_TIME_FLOAT'] ?? null) ?? YII_BEGIN_TIME;

        return [
            'memory' => memory_get_peak_usage(),
            'time' => microtime(true) - $requestStart,
            'messages' => $messages,
        ];
    }

    /**
     * Returns array of profiling models that can be used in a data provider.
     *
     * @return array<int, array{
     *   duration: float,
     *   category: string,
     *   info: string,
     *   level: int,
     *   timestamp: float,
     *   seq: int
     * }>
     */
    protected function getModels(): array
    {
        if ($this->models === null) {
            $this->models = [];

            $timings = Yii::getLogger()->calculateTimings($this->getProfileData()['messages']);

            foreach ($timings as $seq => $profileTiming) {
                $model = self::normalizeTiming($profileTiming, is_int($seq) ? $seq : count($this->models));

                if ($model !== null) {
                    $this->models[] = $model;
                }
            }
        }

        return $this->models;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getToolbarItems(): array
    {
        $profileData = $this->getProfileData();

        return [
            [
                'status' => 'info',
                'title' => 'Total processing time',
                'value' => number_format($profileData['time'] * 1000) . ' ms',
            ],
            [
                'status' => 'info',
                'title' => 'Peak memory',
                'value' => Format::bytesToMb($profileData['memory'], 3),
            ],
        ];
    }

    /**
     * @return array{memory: int, time: float, messages: array<int, array<int|string, mixed>>}
     */
    private function getProfileData(): array
    {
        $data = is_array($this->data) ? $this->data : [];

        return [
            'memory' => Coerce::intOrNull($data['memory'] ?? null) ?? 0,
            'time' => Coerce::floatOrNull($data['time'] ?? null) ?? 0.0,
            'messages' => self::normalizeMessages($data['messages'] ?? []),
        ];
    }

    /**
     * @param array<int|string, mixed>|mixed $messages
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
     * @return array{
     *   duration: float,
     *   category: string,
     *   info: string,
     *   level: int,
     *   timestamp: float,
     *   seq: int
     * }|null
     */
    private static function normalizeTiming(mixed $timing, int $seq): array|null
    {
        if (!is_array($timing)) {
            return null;
        }

        $duration = Coerce::floatOrNull($timing['duration'] ?? null);
        $timestamp = Coerce::floatOrNull($timing['timestamp'] ?? null);

        if ($duration === null || $timestamp === null) {
            return null;
        }

        return [
            'duration' => $duration * 1000,
            'category' => Coerce::stringOrNull($timing['category'] ?? null) ?? '',
            'info' => Coerce::stringOrNull($timing['info'] ?? null) ?? '',
            'level' => Coerce::intOrNull($timing['level'] ?? null) ?? 0,
            'timestamp' => $timestamp * 1000,
            'seq' => $seq,
        ];
    }
}
