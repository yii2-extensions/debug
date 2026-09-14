<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Panel\Dump\{DumpRow, DumpSnapshot};
use PHPForge\Debug\Panel\{PanelIcon, PanelTitle};
use PHPForge\Debug\Storage\HydrationException;
use Yii;
use yii\debug\models\search\LogSearch;
use yii\debug\Panel;
use yii\debug\view\ViewMessage as AdapterMessage;

use function count;

/**
 * Renders the `Yii::debug()` trace messages captured by the Dump collector as dump cards.
 *
 * Presents the pre-rendered dump values without re-serializing; data acquisition lives in
 * {@see \yii\debug\collectors\DumpCollector}.
 */
class DumpPanel extends Panel
{
    /**
     * Captured payload hydrated by {@see hydrate()}, or `null` before hydration.
     */
    private DumpSnapshot|null $snapshot = null;

    /**
     * Renders the detail view with the dump grid powered by the Log search model.
     *
     * @return string Rendered detail view.
     */
    #[Override]
    public function getDetail(): string
    {
        $searchModel = new LogSearch();

        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams(), $this->getModels());

        return Yii::$app->view->render(
            'panels/dump/detail',
            [
                'dataProvider' => $dataProvider,
                'panel' => $this,
                'searchModel' => $searchModel,
            ],
            $this,
        );
    }

    /**
     * Returns the values dumped during the request.
     *
     * @return list<DumpRow> Captured dump rows in capture order.
     */
    public function getDumps(): array
    {
        return $this->snapshot?->entries() ?? [];
    }

    /**
     * Returns the panel display name from the shared title enum.
     *
     * @return string Panel display name.
     */
    #[Override]
    public function getName(): string
    {
        return PanelTitle::DUMP->value;
    }

    /**
     * Returns the icon key from the shared panel icon enum.
     *
     * @return string Toolbar icon key.
     */
    #[Override]
    public function getToolbarIcon(): string
    {
        return PanelIcon::DUMP->value;
    }

    /**
     * Returns whether the capture holds any dump.
     *
     * @return bool `true` when the capture holds at least one dump; `false` otherwise.
     */
    public function hasDumps(): bool
    {
        return $this->getDumps() !== [];
    }

    /**
     * Decodes the captured payload into the typed dump snapshot backing this panel.
     *
     * @param array<string, mixed> $payload Captured panel payload.
     *
     * @throws HydrationException when the payload does not match the snapshot schema.
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->snapshot = DumpSnapshot::fromArray($payload, "$.panels.{$this->id}");
    }

    /**
     * Returns the typed dump rows consumed by the dumps grid.
     *
     * @return list<DumpRow> Rows in capture order, suitable for {@see \yii\data\ArrayDataProvider}.
     */
    protected function getModels(): array
    {
        return $this->getDumps();
    }

    /**
     * Returns the toolbar item showing the number of dumped variables, or `[]` when none were captured.
     *
     * @return array<int, array<string, mixed>> Single-element list with the `info` chip, or `[]`.
     */
    #[Override]
    protected function getToolbarItems(): array
    {
        $dumps = $this->getDumps();

        if ($dumps === []) {
            return [];
        }

        return [
            [
                'status' => 'info',
                'title' => AdapterMessage::DUMP_COUNT->value,
                'value' => count($dumps),
            ],
        ];
    }
}
