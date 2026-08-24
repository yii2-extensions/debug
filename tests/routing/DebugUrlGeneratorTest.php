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
            '/index.php?r=tools%2Fdebug%2Findex&cursor=capture-1',
            $urls->history(['cursor' => 'capture-1', 'tag' => 'discarded']),
            'History URLs must retain the established /index route and discard panel-owned keys.',
        );
        self::assertSame(
            '/index.php?r=tools%2Fdebug%2Fview&tag=capture-1&panel=request&page=2',
            $urls->panel(
                'capture-1',
                'request',
                ['tag' => 'discarded', 'panel' => 'discarded', 'page' => 2],
            ),
            'Panel URLs must keep route-owned tag and panel values authoritative.',
        );
        self::assertSame(
            '/index.php?r=tools%2Fdebug%2Fdb-explain&tag=capture-1&id=query-1',
            $urls->action('/db-explain/', 'capture-1', ['id' => 'query-1']),
            'Panel action URLs must normalize action delimiters and retain additional query parameters.',
        );
    }

    protected function tearDown(): void
    {
        $this->destroyApplication();

        parent::tearDown();
    }
}
