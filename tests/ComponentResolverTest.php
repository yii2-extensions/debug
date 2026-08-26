<?php

declare(strict_types=1);

namespace yii\debug\tests;

use ArrayObject;
use Countable;
use PHPUnit\Framework\Attributes\Group;
use stdClass;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\ComponentResolver;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see ComponentResolver} configuration splitting and Yii object resolution.
 */
#[Group('module')]
final class ComponentResolverTest extends TestCase
{
    public function testClassAndPropertiesAcceptsClassNameString(): void
    {
        self::assertSame(
            [stdClass::class, []],
            ComponentResolver::classAndProperties(stdClass::class),
            'A class-name string must resolve with no properties.',
        );
    }

    public function testClassAndPropertiesRejectsMissingClassNameString(): void
    {
        self::assertSame(
            [null, []],
            ComponentResolver::classAndProperties('yii\debug\DoesNotExist'),
            'A missing class must resolve to `null`.',
        );
    }

    public function testClassAndPropertiesRejectsNonStringClassEntry(): void
    {
        self::assertSame(
            [null, ['flag' => true]],
            ComponentResolver::classAndProperties(['class' => 42, 'flag' => true]),
            'A non-string `class` entry must resolve to `null` and keep the remaining properties.',
        );
    }

    public function testClassAndPropertiesSplitsConfigurationArray(): void
    {
        self::assertSame(
            [stdClass::class, ['flag' => true]],
            ComponentResolver::classAndProperties(['class' => stdClass::class, 'flag' => true]),
            'Array configuration must split into class and remaining properties.',
        );
    }

    public function testClassAndPropertiesUsesDefaultClassWhenArrayOmitsClassKey(): void
    {
        self::assertSame(
            [stdClass::class, ['flag' => true]],
            ComponentResolver::classAndProperties(['flag' => true], stdClass::class),
            'The default class must fill in for arrays without a `class` key.',
        );
        self::assertSame(
            [null, ['flag' => true]],
            ComponentResolver::classAndProperties(['flag' => true]),
            'Without a default, arrays lacking `class` must resolve to `null`.',
        );
    }

    public function testClassAndPropertiesUsesDoubleUnderscoreClassWithPrecedence(): void
    {
        self::assertSame(
            [stdClass::class, ['flag' => true]],
            ComponentResolver::classAndProperties(
                [
                    '__class' => stdClass::class,
                    'class' => ArrayObject::class,
                    'flag' => true,
                ],
            ),
            '`__class` must take precedence and both type keys must be removed from the properties.',
        );
    }

    public function testCreateMappedAcceptsCallable(): void
    {
        $expected = new stdClass();

        self::assertSame(
            $expected,
            ComponentResolver::createMapped(static fn(): stdClass => $expected),
            'A callable action-map entry must be invoked through Yii.',
        );
    }

    public function testCreateMappedAcceptsRegisteredAlias(): void
    {
        $alias = 'debug.component-resolver.alias';

        Yii::$container->set($alias, stdClass::class);

        try {
            self::assertInstanceOf(
                stdClass::class,
                ComponentResolver::createMapped($alias),
                'A registered container alias must resolve through Yii.',
            );
        } finally {
            Yii::$container->clear($alias);
        }
    }

    public function testCreateMappedAcceptsRegisteredInterface(): void
    {
        Yii::$container->set(Countable::class, ArrayObject::class);

        try {
            self::assertInstanceOf(
                ArrayObject::class,
                ComponentResolver::createMapped(Countable::class),
                'A registered interface must resolve through Yii.',
            );
        } finally {
            Yii::$container->clear(Countable::class);
        }
    }

    public function testCreateMappedAcceptsRegisteredSingleton(): void
    {
        $alias = 'debug.component-resolver.singleton';
        $expected = new stdClass();

        Yii::$container->setSingleton($alias, $expected);

        try {
            self::assertSame(
                $expected,
                ComponentResolver::createMapped($alias),
                'A registered singleton must be returned verbatim.',
            );
        } finally {
            Yii::$container->clear($alias);
        }
    }

    public function testCreateMappedPropagatesValidCreationFailure(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Expected action-map creation failure.');

        ComponentResolver::createMapped(
            static function (): never {
                throw new InvalidConfigException('Expected action-map creation failure.');
            },
        );
    }

    public function testCreateMappedReturnsNullForUnresolvableDoubleUnderscoreClass(): void
    {
        self::assertNull(
            ComponentResolver::createMapped(
                [
                    '__class' => 'yii\\debug\\DoesNotExist',
                    'class' => stdClass::class,
                ],
            ),
            'An unresolvable `__class` must not fall back to the lower-precedence `class` entry.',
        );
    }

    public function testCreateMappedReturnsNullWhenCallableProducesNoObject(): void
    {
        self::assertNull(
            ComponentResolver::createMapped(static fn(): string => 'not-an-object'),
            'A callable that produces no object must be rejected.',
        );
    }
}
