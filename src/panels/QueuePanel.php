<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\panels;

use Yii;
use yii\debug\Panel;

/**
 * Placeholder Queue panel — surfaces a chip on the toolbar so the host application can spot queue
 * activity at a glance even when no queue component is wired up yet.
 *
 * The proper implementation lives in the `yiisoft/yii2-queue` package
 * ({@see \yii\queue\debug\Panel}); this placeholder is replaced automatically when that panel is
 * registered.
 *
 * @since 2.1.30
 */
class QueuePanel extends Panel
{
    public function getDetail()
    {
        return Yii::$app->view->render('panels/queue/detail', ['panel' => $this]);
    }

    public function getName()
    {
        return 'Queue';
    }

    public function getToolbarIcon()
    {
        return 'queue';
    }

    /**
     * {@inheritdoc}
     *
     * Disabled by default — the proper implementation lives in {@see \yii\queue\debug\Panel} and
     * is registered automatically when the `yiisoft/yii2-queue` package is installed. Subclasses
     * (or that package's bootstrapper) override this to opt in.
     */
    public function isEnabled()
    {
        return false;
    }
}
