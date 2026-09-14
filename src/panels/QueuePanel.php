<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Panel\{PanelIcon, PanelRenderer, PanelTitle};
use PHPForge\Debug\Panel\Queue\{JobRecord, QueuePanel as QueuePresenter, QueueSnapshot};
use PHPForge\Debug\Storage\HydrationException;
use yii\debug\actions\queue\JobAction;
use yii\debug\{Module, Panel};
use yii\helpers\Url;

use function count;

/**
 * Renders the queue lifecycle events captured by the Queue collector.
 *
 * Delegates the detail view to the framework-neutral {@see QueuePresenter}, feeding it the per-job detail routes;
 * data acquisition lives in {@see \yii\debug\collectors\QueueCollector}.
 */
class QueuePanel extends Panel
{
    /**
     * Captured payload hydrated by {@see hydrate()}, or `null` before hydration.
     */
    private QueueSnapshot|null $snapshot = null;

    /**
     * Renders the detail view through the shared declarative presenter.
     *
     * @return string Rendered panel markup.
     */
    #[Override]
    public function getDetail(): string
    {
        $records = $this->getRecords();

        $jobUrls = [];

        foreach ($records as $index => $_record) {
            $jobUrls[$index] = Url::to(Module::route('queue-job', ['seq' => $index, 'tag' => $this->tag]));
        }

        $snapshot = $this->snapshot ?? new QueueSnapshot([]);

        $view = (new QueuePresenter())->jobUrls($jobUrls)->present($snapshot->jsonSerialize());

        return PanelRenderer::render($this->getName(), $view);
    }

    /**
     * Returns the panel display name from the shared title enum.
     *
     * @return string Panel display name.
     */
    #[Override]
    public function getName(): string
    {
        return PanelTitle::QUEUE->value;
    }

    /**
     * Returns the queue job records captured during the request.
     *
     * @return list<JobRecord> Captured job events in event order.
     */
    public function getRecords(): array
    {
        return $this->snapshot?->entries() ?? [];
    }

    /**
     * Returns the icon key from the shared panel icon enum.
     *
     * @return string Toolbar icon key.
     */
    #[Override]
    public function getToolbarIcon(): string
    {
        return PanelIcon::QUEUE->value;
    }

    /**
     * Decodes the captured payload into the typed queue snapshot backing this panel.
     *
     * @param array<string, mixed> $payload Captured panel payload.
     *
     * @throws HydrationException when the payload does not match the snapshot schema.
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
