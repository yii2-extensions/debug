<?php

declare(strict_types=1);

namespace yii\debug\tests\module;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\exception\Message;
use yii\debug\{LogTarget, Module};
use yii\debug\tests\support\ModuleTestCase;
use yii\debug\tests\support\stub\NotALogTarget;

/**
 * Unit tests for {@see Module} covering log-target instance, class-name, and array-configuration resolution during
 * `bootstrap`, configured properties, and rejection of missing or incompatible target classes.
 */
#[Group('module')]
final class ModuleLogTargetTest extends ModuleTestCase
{
    public function testLogTargetObjectIsAcceptedAsConfig(): void
    {
        $module = new Module('debug');

        $module->logTarget = new LogTarget($module);

        $module->bootstrap(Yii::$app);

        self::assertInstanceOf(
            LogTarget::class,
            $module->logTarget,
            'Object-typed logTarget must be retained verbatim.',
        );
    }

    public function testResolveLogTargetAcceptsArrayConfigWithExtraProperties(): void
    {
        $module = new Module('debug');

        $module->logTarget = ['class' => LogTarget::class, 'levels' => 7];

        $module->bootstrap(Yii::$app);

        self::assertInstanceOf(
            LogTarget::class,
            $module->logTarget,
            "Array config with 'class' key must be resolved via the container.",
        );
        self::assertSame(
            7,
            $module->logTarget->levels,
            "Extra properties in the array config must be applied to the resolved 'LogTarget'.",
        );
    }

    public function testResolveLogTargetAcceptsStringClassName(): void
    {
        $module = new Module('debug');

        $module->logTarget = LogTarget::class;

        $module->bootstrap(Yii::$app);

        self::assertInstanceOf(
            LogTarget::class,
            $module->logTarget,
            "String class name in 'logTarget' must be resolved via the container into a 'LogTarget'.",
        );
    }

    public function testThrowInvalidConfigExceptionWhenLogTargetClassDoesNotResolveToLogTarget(): void
    {
        $module = new Module('debug');

        $module->logTarget = NotALogTarget::class;

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::LOG_TARGET_INSTANCE_INVALID->getMessage(),
        );

        $module->bootstrap(Yii::$app);
    }

    public function testThrowInvalidConfigExceptionWhenLogTargetConfigDeclaresMissingClass(): void
    {
        $module = new Module('debug');

        $module->logTarget = ['class' => 'No\\Such\\LogTarget'];

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::LOG_TARGET_CLASS_INVALID->getMessage(),
        );

        $module->bootstrap(Yii::$app);
    }
}
