<?php

declare(strict_types=1);

namespace yii\debug\tests\service;

use PHPUnit\Framework\Attributes\Group;
use yii\base\InvalidConfigException;
use yii\debug\exception\Message;
use yii\debug\{LogTarget, Module};
use yii\debug\service\LogTargetFactory;
use yii\debug\tests\support\ModuleTestCase;
use yii\debug\tests\support\stub\NotALogTarget;

/**
 * Unit tests for {@see LogTargetFactory} resolving the configured debug log target.
 */
#[Group('service')]
final class LogTargetFactoryTest extends ModuleTestCase
{
    public function testCreateAcceptsAnArrayConfigurationWithExtraProperties(): void
    {
        $module = new Module('debug');

        $module->logTarget = ['class' => LogTarget::class, 'levels' => 7];

        $target = (new LogTargetFactory($module))->create();

        self::assertSame(
            LogTarget::class,
            $target::class,
            "A 'class' entry must be resolved through the container.",
        );
        self::assertSame(
            7,
            $target->levels,
            'Remaining entries must be applied as properties.',
        );
    }

    public function testCreateAcceptsAStringClassName(): void
    {
        $module = new Module('debug');

        $module->logTarget = LogTarget::class;

        self::assertSame(
            LogTarget::class,
            (new LogTargetFactory($module))->create()::class,
            'Class name must be resolved through the container.',
        );
    }

    public function testCreateHandsTheModuleToTheResolvedTarget(): void
    {
        $module = new Module('debug');

        $module->logTarget = LogTarget::class;

        self::assertSame(
            $module,
            (new LogTargetFactory($module))->create()->module,
            'Owning module must reach the constructor.',
        );
    }

    public function testCreateReturnsAnAlreadyInstantiatedTargetVerbatim(): void
    {
        $module = new Module('debug');
        $target = new LogTarget($module);

        $module->logTarget = $target;

        self::assertSame(
            $target,
            (new LogTargetFactory($module))->create(),
            'An instance must be handed back untouched.',
        );
    }

    public function testThrowInvalidConfigExceptionForAClassOutsideTheLogTargetContract(): void
    {
        $module = new Module('debug');

        $module->logTarget = NotALogTarget::class;

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::LOG_TARGET_INSTANCE_INVALID->getMessage(),
        );

        (new LogTargetFactory($module))->create();
    }

    public function testThrowInvalidConfigExceptionForAConfigurationDeclaringAMissingClass(): void
    {
        $module = new Module('debug');

        $module->logTarget = ['class' => 'No\\Such\\LogTarget'];

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::LOG_TARGET_CLASS_INVALID->getMessage(),
        );

        (new LogTargetFactory($module))->create();
    }
}
