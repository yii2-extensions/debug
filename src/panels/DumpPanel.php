<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Panel\Dump\{DumpRow, DumpSnapshot};
use Yii;
use yii\debug\models\search\LogSearch;
use yii\debug\Panel;

use function count;

/**
 * Renders the `Yii::debug()` trace messages captured by the Dump collector as dump cards.
 *
 * Presents the pre-rendered dump values without re-serializing; data acquisition lives in
 * {@see \yii\debug\collectors\DumpCollector}.
 */
class DumpPanel extends Panel
{
    protected const string ICON = 'dump';
    protected const string NAME = 'Dump';

    private DumpSnapshot|null $snapshot = null;

    /**
     * Renders the detail view with the dump grid powered by the Log search model.
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
     * @return list<DumpRow> Captured dump rows in capture order.
     */
    public function getDumps(): array
    {
        return $this->snapshot?->entries() ?? [];
    }

    public function hasDumps(): bool
    {
        return $this->getDumps() !== [];
    }

    /**
     * @param array<string, mixed> $payload
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
                'title' => 'Number of dumped variables',
                'value' => count($dumps),
            ],
        ];
    }
}
