<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\controllers;

use Exception;
use Yii;
use yii\debug\models\search\Debug;
use yii\debug\Module;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

use function array_keys;
use function reset;
use function sleep;
use function str_contains;

/**
 * Debugger controller provides browsing over available debug logs.
 *
 * @see \yii\debug\Panel
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 *
 * @since 2.0
 */
class DefaultController extends Controller
{
    public $layout = 'main';
    /**
     * @var Module owner module.
     */
    public $module;
    /**
     * @var array the summary data (e.g. URL, time)
     */
    public array $summary = [];

    private array $_manifest = [];

    public function actions(): array
    {
        $actions = [];

        foreach ($this->module->panels as $panel) {
            $actions = [...$actions, ...$panel->actions];
        }

        return $actions;
    }

    /**
     * @throws BadRequestHttpException
     */
    public function beforeAction($action): bool
    {
        Yii::$app->response->format = Response::FORMAT_HTML;

        return parent::beforeAction($action);
    }

    /**
     * Index action.
     *
     * @throws Exception|NotFoundHttpException
     */
    public function actionIndex(): string
    {
        $searchModel = new Debug();
        $dataProvider = $searchModel->search($_GET, $this->getManifest());

        // load latest request
        $tags = array_keys($this->getManifest());

        if (empty($tags)) {
            throw new Exception('No debug data have been collected yet, try browsing the website first.');
        }

        $tag = reset($tags);
        $this->loadData($tag);

        return $this->render('index', [
            'panels' => $this->module->panels,
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
            'manifest' => $this->getManifest(),
        ]);
    }

    /**
     * View action.
     *
     * @param string|null $tag debug data tag.
     * @param string|null $panel debug panel ID.
     *
     * @throws NotFoundHttpException if debug data aren't found.
     *
     * @see \yii\debug\Panel
     */
    public function actionView(string $tag = null, string $panel = null): string
    {
        if ($tag === null) {
            $tags = array_keys($this->getManifest());
            $tag = reset($tags);
        }

        $this->loadData($tag);

        $activePanel = $this->module->panels[$panel] ?? $this->module->panels[$this->module->defaultPanel];

        if ($activePanel->hasError()) {
            Yii::$app->errorHandler->handleException($activePanel->getError());
        }

        return $this->render('view', [
            'tag' => $tag,
            'summary' => $this->summary,
            'manifest' => $this->getManifest(),
            'panels' => $this->module->panels,
            'activePanel' => $activePanel,
        ]);
    }

    /**
     * Toolbar action.
     *
     * @throws NotFoundHttpException if debug data aren't found.
     */
    public function actionToolbar(string $tag): string
    {
        $this->loadData($tag, 5);

        return $this->renderPartial('toolbar', [
            'tag' => $tag,
            'panels' => $this->module->panels,
            'position' => 'bottom',
            'defaultHeight' => $this->module->defaultHeight,
        ]);
    }

    /**
     * Download mail action.
     *
     * @throws NotFoundHttpException if mail file isn't found.
     */
    public function actionDownloadMail(string $file): Response|\yii\console\Response
    {
        $filePath = Yii::getAlias($this->module->panels['mail']->mailPath) . '/' . basename($file);

        if ((str_contains($file, '\\') || str_contains($file, '/')) || !is_file($filePath)) {
            throw new NotFoundHttpException('Mail file not found');
        }

        return Yii::$app->response->sendFile($filePath);
    }

    /**
     * Loads debug data.
     *
     * @param string $tag debug data tag.
     * @param int $maxRetry maximum numbers of tag retrieval attempts.
     *
     * @throws NotFoundHttpException if specified tag isn't found.
     */
    public function loadData(string $tag, int $maxRetry = 0): void
    {
        // retry loading debug data because the debug data is logged in shutdown function
        // which may be delayed in some environment if xdebug is enabled.
        // See: https://github.com/yiisoft/yii2/issues/1504
        for ($retry = 0; $retry <= $maxRetry; ++$retry) {
            $manifest = $this->getManifest($retry > 0);
            if (isset($manifest[$tag])) {
                $data = $this->module->logTarget->loadTagToPanels($tag);
                $this->summary = $data['summary'];

                return;
            }
            sleep(1);
        }

        throw new NotFoundHttpException("Unable to find debug data tagged with '$tag'.");
    }

    protected function getManifest(bool $forceReload = false): array
    {
        if ($this->_manifest === [] || $forceReload) {
            if ($forceReload) {
                clearstatcache();
            }

            $this->_manifest = $this->module->logTarget->loadManifest();
        }

        return $this->_manifest;
    }
}
