<?php

declare(strict_types=1);

namespace yii\debug\tests\routing;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\routing\DebugUrlGenerator;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see DebugUrlGenerator} and its canonical Yii debugger routes.
 */
#[Group('routing')]
final class DebugUrlGeneratorTest extends TestCase
{
    public function testGeneratesCanonicalRoutesForCustomModuleId(): void
    {
        $this->mockWebApplication();

        $urls = new DebugUrlGenerator('/tools/debug/');

        self::assertSame(
            '/index.php?r=tools%2Fdebug%2Fview&tag=capture-1&panel=request&page=2',
            $urls->panel(
                'capture-1',
                'request',
                ['tag' => 'discarded', 'panel' => 'discarded', 'page' => 2],
            ),
            'Panel URLs must keep route-owned tag and panel values authoritative.',
        );
    }

    public function testQueryKeepsNumericExtrasAndEveryAdditionalParameter(): void
    {
        $this->mockWebApplication();

        $urls = new DebugUrlGenerator();

        self::assertSame(
            '/index.php?r=debug%2Fview&tag=capture-1&panel=request&1=extra&sort=duration&page=2',
            $urls->panel(
                'capture-1',
                'request',
                [0 => 'route', 1 => 'extra', 'sort' => 'duration', 'page' => 2, 'tag' => 'discarded'],
            ),
            'Numeric extras and every additional parameter must survive.',
        );
    }

    protected function tearDown(): void
    {
        $this->destroyApplication();

        parent::tearDown();
    }
}
