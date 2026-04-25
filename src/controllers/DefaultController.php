<?php

declare(strict_types=1);

namespace yii\debug\controllers;

use Yii;
use yii\debug\models\search\Debug;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Debugger controller provides browsing over available debug logs.
 */
class DefaultController extends Controller
{
    public $layout = 'main';
    /**
     * @var \yii\debug\Module owner module.
     */
    public $module;
    /**
     * @var array the summary data (e.g. URL, time)
     */
    public $summary;

    /**
     * @var array
     */
    private $_manifest;

    /**
     * Download mail action
     *
     * @param string $file
     * @throws NotFoundHttpException
     * @return Response|\yii\console\Response
     */
    public function actionDownloadMail($file)
    {
        $filePath = Yii::getAlias($this->module->panels['mail']->mailPath) . '/' . basename($file);

        if ((mb_strpos($file, '\\') !== false || mb_strpos($file, '/') !== false) || !is_file($filePath)) {
            throw new NotFoundHttpException('Mail file not found');
        }

        return Yii::$app->response->sendFile($filePath);
    }

    /**
     * Index action
     *
     * @throws NotFoundHttpException
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new Debug();
        $dataProvider = $searchModel->search($_GET, $this->getManifest());

        // load latest request
        $tags = array_keys($this->getManifest());

        if (empty($tags)) {
            throw new \Exception('No debug data have been collected yet, try browsing the website first.');
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
     * Renders the full `phpinfo()` output in a standalone page (no sidebar).
     *
     * This action is intentionally kept outside the panel registry so the entry never appears on the sidebar nav it is
     * linked from the Configuration panel's CTA and opens in a new tab.
     */
    public function actionPhpInfo()
    {
        return $this->render('phpinfo');
    }

    public function actions()
    {
        $actions = [];
        foreach ($this->module->panels as $panel) {
            $actions = array_merge($actions, $panel->actions);
        }

        return $actions;
    }

    /**
     * Toolbar action
     *
     * @param string $tag
     * @throws NotFoundHttpException
     * @return string
     */
    public function actionToolbar($tag)
    {
        $this->loadData($tag, 5);

        return $this->renderPartial(
            'toolbar',
            [
                'tag' => $tag,
                'panels' => $this->module->panels,
                'position' => $this->module->toolbarPosition,
                'defaultHeight' => $this->module->defaultHeight,
            ],
        );
    }

    /**
     * Toolbar data action
     *
     * @param string $tag
     *
     * @throws NotFoundHttpException
     *
     * @return array<string, mixed>
     */
    public function actionToolbarData($tag)
    {
        if (Yii::$app instanceof \yii\web\Application) {
            Yii::$app->getResponse()->format = Response::FORMAT_JSON;
        }

        try {
            $this->loadData($tag, 5);
        } catch (NotFoundHttpException) {
            // Tag rotated out of history. Return a JSON 404 so the toolbar can degrade
            // gracefully without triggering the host application's HTML error page.
            Yii::$app->getResponse()->setStatusCode(404);

            return ['error' => 'Debug tag not found.', 'tag' => $tag];
        }

        $items = [];
        foreach ($this->module->panels as $id => $panel) {
            $data = $panel->getToolbarData();

            if (empty($data)) {
                continue;
            }

            if (!isset($data['id'])) {
                $data['id'] = $id;
            }
            if (!isset($data['title'])) {
                $data['title'] = $panel->getName();
            }
            if (!isset($data['url'])) {
                $data['url'] = $panel->getUrl();
            }

            $items[] = $data;
        }

        $configPanel = $this->module->panels['config'] ?? null;

        $yiiVersion = null;
        $phpVersion = null;

        if ($configPanel !== null && isset($configPanel->data['application']['yii'], $configPanel->data['php']['version'])) {
            $yiiVersion = (string) $configPanel->data['application']['yii'];
            $phpVersion = (string) $configPanel->data['php']['version'];
        }

        $iconBaseUrl = '';
        try {
            $published = Yii::$app->assetManager->publish(Yii::getAlias('@yii/debug/assets'));

            $iconBaseUrl = rtrim($published[1], '/') . '/svg/';
        } catch (\Throwable $e) {
            // Asset manager not configured (e.g. unit test environment) — keep empty so the
            // toolbar JS falls back to the bundled PNG logo and skips chip icons.
        }

        return [
            'title' => 'Yii Debugger',
            'logo' => $iconBaseUrl !== '' ? $iconBaseUrl . 'yii.svg' : $this->module::getYiiLogo(),
            'logoFallback' => $this->module::getYiiLogo(),
            'indexUrl' => Url::toRoute(['/' . $this->module->getUniqueId() . '/default/index']),
            'configUrl' => $configPanel !== null
                ? Url::toRoute(
                    ['/' . $this->module->getUniqueId() . '/default/view', 'tag' => $tag, 'panel' => 'config'],
                )
                : null,
            'phpInfoUrl' => Url::toRoute(['/' . $this->module->getUniqueId() . '/default/php-info']),
            'yiiVersion' => $yiiVersion,
            'phpVersion' => $phpVersion,
            'iconBaseUrl' => $iconBaseUrl,
            'tag' => $tag,
            'position' => $this->module->toolbarPosition,
            'defaultHeight' => $this->module->defaultHeight,
            'items' => $items,
        ];
    }

    /**
     * @see \yii\debug\Panel
     *
     * @param string|null $tag debug data tag.
     * @param string|null $panel debug panel ID.
     *
     * @throws NotFoundHttpException if debug data not found.
     *
     * @return mixed response.
     */
    public function actionView($tag = null, $panel = null)
    {
        if ($tag === null) {
            $tags = array_keys($this->getManifest());
            $tag = reset($tags);
        }

        $this->loadData($tag);

        if ($panel !== null && isset($this->module->panels[$panel])) {
            $activePanel = $this->module->panels[$panel];
        } else {
            $activePanel = $this->module->panels[$this->module->defaultPanel];
        }

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
     * @throws \yii\web\BadRequestHttpException
     */
    public function beforeAction($action)
    {
        Yii::$app->response->format = Response::FORMAT_HTML;
        return parent::beforeAction($action);
    }

    /**
     * @param string $tag debug data tag.
     * @param int $maxRetry maximum numbers of tag retrieval attempts.
     *
     * @throws NotFoundHttpException if specified tag not found.
     */
    public function loadData($tag, $maxRetry = 0)
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

    /**
     * @param bool $forceReload
     * @return array
     */
    protected function getManifest($forceReload = false)
    {
        if ($this->_manifest === null || $forceReload) {
            if ($forceReload) {
                clearstatcache();
            }
            $this->_manifest = $this->module->logTarget->loadManifest();
        }

        return $this->_manifest;
    }
}
