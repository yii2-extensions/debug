<?php

declare(strict_types=1);

namespace yii\debug\tests\service;

use PHPForge\Debug\Helper\Icon;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use yii\debug\exception\Message;
use yii\debug\service\YiiLogo;
use yii\debug\tests\support\TestCase;

use function base64_encode;

/**
 * Unit tests for {@see YiiLogo} serving the Yii mark as a cached data URI.
 */
#[Group('service')]
final class YiiLogoTest extends TestCase
{
    public function testDataUriComposesTheUriFromThePackagedIcon(): void
    {
        self::assertSame(
            'data:image/svg+xml;base64,' . base64_encode(Icon::render('yii')),
            YiiLogo::dataUri(),
            'Packaged mark must be base64-encoded into a data URI.',
        );
    }

    public function testDataUriKeepsTheComposedUriCached(): void
    {
        $uri = YiiLogo::dataUri();

        $this->setInaccessibleStaticProperty(Icon::class, 'cache', ['yii' => '']);

        self::assertSame(
            $uri,
            YiiLogo::dataUri(),
            'Packaged asset must be read once.',
        );
    }

    public function testResetDropsTheCachedUri(): void
    {
        YiiLogo::set('data:image/svg+xml;base64,FAKE');

        YiiLogo::reset();

        self::assertSame(
            'data:image/svg+xml;base64,' . base64_encode(Icon::render('yii')),
            YiiLogo::dataUri(),
            'Packaged mark must be composed again.',
        );
    }

    public function testSetReplacesTheServedUri(): void
    {
        YiiLogo::set('data:image/svg+xml;base64,FAKE');

        self::assertSame(
            'data:image/svg+xml;base64,FAKE',
            YiiLogo::dataUri(),
            'Configured URI must round-trip.',
        );
    }

    public function testThrowRuntimeExceptionWhenThePackagedIconIsEmpty(): void
    {
        $this->setInaccessibleStaticProperty(Icon::class, 'cache', ['yii' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            Message::YII_LOGO_UNREADABLE->getMessage(),
        );

        YiiLogo::dataUri();
    }

    protected function setUp(): void
    {
        parent::setUp();

        YiiLogo::reset();
    }

    protected function tearDown(): void
    {
        $this->setInaccessibleStaticProperty(Icon::class, 'cache', []);

        YiiLogo::reset();

        parent::tearDown();
    }
}
