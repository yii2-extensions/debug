<?php

declare(strict_types=1);

namespace yii\debug\tests\support;

use Yii;
use yii\db\Connection;
use yii\debug\actions\Action as DebugAction;
use yii\debug\Module;
use yii\web\AssetManager;

use function mkdir;
use function unlink;

/**
 * Shared application fixtures and lifecycle dispatch for standalone debugger action tests.
 */
abstract class ActionTestCase extends TestCase
{
    protected function bootDebugModule(bool $collectorless = false): Module
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'assetManager' => [
                        'class' => AssetManager::class,
                        'basePath' => dirname(__DIR__, 2) . '/runtime/assets',
                        'baseUrl' => '/assets',
                    ],
                    'db' => ['class' => Connection::class, 'dsn' => 'sqlite::memory:'],
                ],
            ],
        );

        @mkdir(Yii::getAlias('@runtime/assets'), 0o777, true);

        $module = $collectorless
            ? new class ('debug') extends Module {
                protected function coreCollectors(): array
                {
                    return [];
                }
            }
        : new Module('debug');

        $module->allowedIPs = ['*'];

        Yii::$app->setModule(
            'debug',
            $module,
        );

        $module->bootstrap(Yii::$app);

        // Purge any residue from prior tests so each run starts with an empty manifest.
        $dataPath = Yii::getAlias($module->dataPath);

        @mkdir($dataPath, 0o777, true);

        $files = glob("{$dataPath}/*.json");

        foreach ($files === false ? [] : $files as $file) {
            @unlink($file);
        }

        return $module;
    }

    /**
     * Dispatches a wired standalone action through its full run lifecycle.
     *
     * @param array<string, mixed> $params Route parameters bound to the action.
     */
    protected function runDebugAction(DebugAction $action, Module $module, array $params = []): mixed
    {
        $action->setModule($module);

        Yii::$app->requestedAction = $action;
        Yii::$app->requestedRoute = $action->getUniqueId();

        return $action->runWithParams($params);
    }
}
