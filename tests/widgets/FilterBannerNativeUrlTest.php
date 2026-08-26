<?php

declare(strict_types=1);

namespace yii\debug\tests\widgets;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\actions\IndexAction;
use yii\debug\{LogTarget, Module};
use yii\debug\models\search\LogSearch;
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\FilterBanner;
use yii\helpers\{Html, Url};
use yii\web\Controller;

/**
 * Integration tests for {@see FilterBanner} native current-route URL generation.
 */
#[Group('widget')]
#[Group('filter-banner')]
final class FilterBannerNativeUrlTest extends TestCase
{
    public function testFallsBackToRequestedActionRouteWhenRequestedRouteIsEmpty(): void
    {
        $this->mockWebApplication();

        Yii::$app->getRequest()->setUrl('dummy');

        $module = new Module('debug');
        $module->logTarget = new LogTarget($module);

        $action = new IndexAction('index');
        $action->setModule($module);

        Yii::$app->requestedAction = $action;
        Yii::$app->requestedRoute = '';

        $_GET['Log'] = ['category' => 'app', 'message' => 'login'];
        $_GET['sort'] = 'time';
        $_GET['page'] = 3;

        $html = FilterBanner::widget(['searchModel' => new LogSearch()]);
        $expected = Url::to(
            [
                '/debug/index',
                'Log' => ['message' => 'login'],
                'sort' => 'time',
            ],
        );

        self::assertStringContainsString(
            'href="' . Html::encode($expected) . '"',
            $html,
            'An empty requested route must fall back to the requested-action route.',
        );
    }

    public function testPreservesStandaloneRouteAndRemainingQuery(): void
    {
        $this->mockWebApplication();

        Yii::$app->getRequest()->setUrl('dummy');

        $module = new Module('debug');
        $module->logTarget = new LogTarget($module);

        $action = new IndexAction('index');
        $action->setModule($module);

        Yii::$app->requestedAction = $action;
        Yii::$app->requestedRoute = 'debug/index';

        $_GET['Log'] = ['category' => 'app', 'message' => 'login'];
        $_GET['sort'] = 'time';
        $_GET['page'] = 3;

        $html = FilterBanner::widget(['searchModel' => new LogSearch()]);
        $expected = Url::to(
            [
                '/debug/index',
                'Log' => ['message' => 'login'],
                'sort' => 'time',
            ],
        );

        self::assertStringContainsString(
            'href="' . Html::encode($expected) . '"',
            $html,
            'The URL must preserve the current route and remaining query.',
        );
    }

    public function testUsesCurrentControllerRouteWhenRequestedRouteIsEmpty(): void
    {
        $this->mockWebApplication();

        Yii::$app->getRequest()->setUrl('dummy');

        $module = new Module('debug');
        $module->logTarget = new LogTarget($module);

        Yii::$app->controller = new Controller('site', Yii::$app);
        Yii::$app->requestedAction = null;
        Yii::$app->requestedRoute = '';

        $_GET['Log'] = ['category' => 'app', 'message' => 'login'];
        $_GET['sort'] = 'time';

        $html = FilterBanner::widget(['searchModel' => new LogSearch()]);

        self::assertStringContainsString('r=site', $html, 'Controller route must drive the removal links.');
    }
}
