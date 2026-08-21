<?php

declare(strict_types=1);

namespace yii\debug\actions;

use PHPForge\Debug\Helper\Coerce;
use Yii;
use yii\base\Exception;
use yii\debug\exception\Message;
use yii\debug\models\search\DebugSearch;

use function array_key_first;
use function array_values;

/**
 * Renders the request history page listing every captured debug entry.
 */
class IndexAction extends Action
{
    /**
     * Runs the action.
     *
     * @throws Exception When no debug entries have been captured yet.
     *
     * @return string Rendered index view.
     */
    public function run(): string
    {
        $manifest = $this->getManifest();

        if ($manifest === []) {
            throw new Exception(
                Message::DEBUG_DATA_EMPTY->getMessage(),
            );
        }

        $searchModel = new DebugSearch();

        $dataProvider = $searchModel->search(Yii::$app->getRequest()->getQueryParams(), array_values($manifest));

        $tag = array_key_first($manifest);

        $this->loadData($tag);

        $cursor = Coerce::string(Yii::$app->getRequest()->get('cursor'));

        $this->prepareIndexShell($manifest, $cursor);

        return $this->render(
            'index',
            [
                'dataProvider' => $dataProvider,
                'manifest' => $manifest,
                'panels' => $this->getDebugModule()->panels,
                'searchModel' => $searchModel,
            ],
        );
    }
}
