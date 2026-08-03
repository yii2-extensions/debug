<?php

declare(strict_types=1);

namespace yii\debug\tests\helpers;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\helpers\Icon;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see Icon} covering the file-resolution and in-memory cache branches of the SVG renderer.
 */
#[Group('helpers')]
#[Group('icon')]
final class IconTest extends TestCase
{
    public function testRenderReturnsCachedMarkupWithoutReadingFile(): void
    {
        $this->setInaccessibleStaticProperty(
            Icon::class,
            'cache',
            ['cached-only' => '<svg id="cached"></svg>'],
        );

        self::assertSame(
            '<svg id="cached"></svg>',
            Icon::render('cached-only'),
            'A cached icon must be returned even when no matching file exists.',
        );

        $this->setInaccessibleStaticProperty(Icon::class, 'cache', []);
    }

    public function testRenderReturnsEmptyStringWhenSvgFileMissing(): void
    {
        self::assertSame(
            '',
            Icon::render('this-icon-file-does-not-exist'),
            'Missing icon files must collapse to an empty string.',
        );
    }

    public function testRenderReturnsSanitizedMarkupForBundledIcon(): void
    {
        $markup = Icon::render('clock');

        self::assertNotSame(
            '',
            $markup,
            'Bundled icons must produce non-empty SVG markup.',
        );
        self::assertStringContainsString(
            '<svg',
            $markup,
            'Rendered markup must include the opening `<svg>` tag.',
        );
    }

}
