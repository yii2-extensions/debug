<?php

declare(strict_types=1);

namespace yiiunit\debug;

use Yii;
use yii\base\Event;
use yii\caching\FileCache;
use yii\debug\Module;
use yii\log\Dispatcher;

class ModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockWebApplication();
    }

    /**
     * @dataProvider \yiiunit\debug\providers\Data::checkAccess
     *
     * @param string $userIp
     * @param bool $expectedResult
     *
     * @throws \ReflectionException
     */
    public function testCheckAccess(array $allowedIPs, $userIp, $expectedResult): void
    {
        $module = new Module('debug');
        $module->allowedIPs = $allowedIPs;
        $_SERVER['REMOTE_ADDR'] = $userIp;
        $this->assertEquals($expectedResult, $this->invoke($module, 'checkAccess'));
    }

    /**
     * Test to ensure logTarget can take object as config
     */
    public function testLogTargetObject(): void
    {
        $module = new Module('debug');
        $module->logTarget = new \yii\debug\LogTarget($module);
        $module->bootstrap(Yii::$app);

        $this->assertInstanceOf('yii\debug\LogTarget', $module->logTarget);
    }

    /**
     * Test to verify toolbars html
     */
    public function testGetToolbarHtml(): void
    {
        $module = new Module('debug');

        $module->bootstrap(Yii::$app);

        $mock = $this->createMock(Dispatcher::class);
        $mock->setMethods(['dispatch']);

        Yii::getLogger()->dispatcher = $mock;

        $this->assertEquals(<<<HTML
<div id="yii-debug-toolbar" data-url="/index.php?r=debug%2Fdefault%2Ftoolbar&amp;tag={$module->logTarget->tag}" data-skip-urls="[]" style="display:none" class="yii-debug-toolbar-bottom"></div>
HTML
            , $module->getToolbarHtml());
    }

    /**
     * Test to ensure toolbar is never cached
     */
    public function testNonCachedToolbarHtml(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        Yii::$app->setModule('debug', $module);

        $module->bootstrap(Yii::$app);

        $mock = $this->createMock(Dispatcher::class);
        $mock->setMethods(['dispatch']);

        Yii::getLogger()->dispatcher = $mock;
        Yii::$app->set('cache', new FileCache(['cachePath' => '@yiiunit/debug/runtime/cache']));

        $view = Yii::$app->view;

        for ($i = 0; $i <= 1; $i++) {
            ob_start();

            $module->logTarget->tag = 'tag' . $i;

            if ($view->beginCache(__FUNCTION__, ['duration' => 3])) {
                $module->renderToolbar(new Event(['sender' => $view]));
                $view->endCache();
            }

            $output[$i] = ob_get_clean();
        }

        $this->assertNotEquals($output[0], $output[1]);
    }

    /**
     * Making sure debug toolbar does not error
     * in case module ID is not "debug".
     *
     * @see https://github.com/yiisoft/yii2-debug/pull/176/
     */
    public function testToolbarWithCustomModuleID(): void
    {
        $moduleID = 'my_debug';

        $module = new Module($moduleID);
        $module->allowedIPs = ['*'];

        Yii::$app->setModule($moduleID, $module);

        $module->bootstrap(Yii::$app);

        $mock = $this->createMock(Dispatcher::class);
        $mock->setMethods(['dispatch']);

        Yii::getLogger()->dispatcher = $mock;

        $view = Yii::$app->view;

        ob_start();
        $module->renderToolbar(new Event(['sender' => $view]));
        $output = ob_get_clean();

        $this->assertThat($output, $this->logicalOr(
            $this->matches('%Adata-url="/my_debug%A'),
            $this->matches('%Adata-url="/index.php?r=my_debug%A')
        ));
    }

    public function testDefaultVersion(): void
    {
        Yii::$app->extensions['yiisoft/yii2-debug'] = [
            'name' => 'yiisoft/yii2-debug',
            'version' => '2.0.7',
        ];

        $module = new Module('debug');

        $this->assertEquals('2.2', $module->getVersion());
    }
}
