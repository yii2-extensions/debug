<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Panel\Queue\{JobRecord, QueueSnapshot};
use Yii;
use yii\debug\actions\queue\JobAction;
use yii\debug\models\search\QueueSearch;
use yii\debug\Panel;

use function count;

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
        // Yii lifecycle convention: the parent chain is a no-op today, so removing this call is unobservable.
        // @infection-ignore-all
        parent::init();

        $this->actions['queue-job'] = JobAction::class;
    }

    /**
     * Builds the toolbar items.
     *
     * Hides the button when no queue events were captured, and surfaces an `Errors` chip in `danger` when at least one
     * error event was captured.
     *
     * @return array<int, array<string, mixed>> Toolbar items, or `[]` when no events were captured.
     */
    #[Override]
    protected function getToolbarItems(): array
    {
        $records = $this->getRecords();

        if ($records === []) {
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
}
