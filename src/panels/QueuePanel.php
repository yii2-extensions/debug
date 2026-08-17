<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Panel\Queue\{JobRecord, QueueSnapshot};
use Yii;
use yii\debug\actions\queue\JobAction;
use yii\debug\models\search\QueueSearch;
use yii\debug\Panel;

use function class_exists;
use function count;
use function interface_exists;
use function is_array;
use function is_object;
use function is_string;
use function is_subclass_of;

/**
 * Renders the queue lifecycle events captured by the Queue collector.
 *
 * Presents every `afterPush`, `afterExec`, and `afterError` record emitted by any class extending `yii\queue\Queue`
 * from `yiisoft/yii2-queue`; data acquisition lives in {@see \yii\debug\collectors\QueueCollector}. When the package
 * is not installed the empty-state view is shown.
 */
class QueuePanel extends Panel
{
    protected const string ICON = 'queue';
    protected const string NAME = 'Queue';

    /**
     * Queue base class used to detect configured queue components; the abstract base `yii\queue\Queue` from
     * `yiisoft/yii2-queue` that every concrete driver extends.
     */
    private const string QUEUE_BASE_CLASS = 'yii\queue\Queue';

    private QueueSnapshot|null $snapshot = null;

    /**
     * Renders the detail view with the queue cards list.
     */
    #[Override]
    public function getDetail(): string
    {
        $searchModel = new QueueSearch();

        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams(), $this->getRecords());

        return Yii::$app->view->render(
            'panels/queue/detail',
            [
                'dataProvider' => $dataProvider,
                'panel' => $this,
                'searchModel' => $searchModel,
            ],
            $this,
        );
    }

    /**
     * @return list<JobRecord> Captured job events in event order.
     */
    public function getRecords(): array
    {
        return $this->snapshot?->entries() ?? [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->snapshot = QueueSnapshot::fromArray($payload, "$.panels.{$this->id}");
    }

    /**
     * Registers the `queue-job` action.
     */
    public function init(): void
    {
        parent::init();

        $this->actions['queue-job'] = [
            'class' => JobAction::class,
            'panel' => $this,
        ];
    }

    /**
     * Builds the toolbar items.
     *
     * Hides the button entirely on apps that don't configure any queue component, and surfaces an `Errors` chip in
     * `danger` when at least one error event was captured.
     *
     * @return array<int, array<string, mixed>> Toolbar items, or `[]` when no queue component is configured and no
     * events were captured.
     */
    #[Override]
    protected function getToolbarItems(): array
    {
        $records = $this->getRecords();

        if ($records === [] && $this->hasQueueComponentConfigured() === false) {
            return [];
        }

        $errors = 0;

        foreach ($records as $record) {
            if ($record->eventType === JobRecord::TYPE_ERROR) {
                $errors++;
            }
        }

        $items = [['value' => count($records)]];

        if ($errors > 0) {
            $items[] = [
                'label' => 'Errors',
                'status' => 'danger',
                'value' => $errors,
            ];
        }

        return $items;
    }

    /**
     * Tests whether a single `Yii::$app->components` entry references one of the known queue base classes.
     *
     * Accepts the three shapes Yii allows: a class-name string, a config array with a `class` key, or an
     * already-instantiated component object. For object inputs only `is_subclass_of` matches, since the queue base
     * class is abstract.
     */
    private static function componentMatchesQueueBase(mixed $config): bool
    {
        if (is_object($config)) {
            return is_subclass_of($config, self::QUEUE_BASE_CLASS);
        }

        $class = null;

        if (is_string($config)) {
            $class = $config;
        } elseif (is_array($config) && is_string($config['class'] ?? null)) {
            $class = $config['class'];
        }

        if ($class === null) {
            return false;
        }

        return $class === self::QUEUE_BASE_CLASS || is_subclass_of($class, self::QUEUE_BASE_CLASS);
    }

    /**
     * Returns whether the application registers at least one queue component.
     *
     * Walks `Yii::$app->components` without instantiating lazy components so the panel can keep the Queue button
     * visible on apps that DO configure queues, even when no jobs were pushed in the current request (mirroring the
     * Database panel's behavior). Pre-loads the abstract base via `class_exists` so the `is_subclass_of` check works
     * when the queue package was not loaded yet; when the package is missing entirely, the check returns `false` and
     * the panel stays hidden.
     */
    private function hasQueueComponentConfigured(): bool
    {
        if (
            class_exists(self::QUEUE_BASE_CLASS, false) === false
            && interface_exists(self::QUEUE_BASE_CLASS, false) === false
        ) {
            class_exists(self::QUEUE_BASE_CLASS);
        }

        foreach (Yii::$app->getComponents(true) as $config) {
            if (self::componentMatchesQueueBase($config)) {
                return true;
            }
        }

        return false;
    }
}
