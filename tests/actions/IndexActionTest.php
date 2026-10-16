<?php

declare(strict_types=1);

namespace yii\debug\tests\actions;

use Exception;
use PHPForge\Debug\Panel\Config\ConfigSnapshot;
use PHPUnit\Framework\Attributes\{Group, TestWith};
use Yii;
use yii\debug\actions\{IndexAction, ViewAction};
use yii\debug\exception\Message;
use yii\debug\tests\support\ActionTestCase;
use yii\debug\widgets\shell\ShellContext;

/**
 * Unit tests for {@see IndexAction} covering history rendering with a cursor, captured shell metadata, the comparison
 * form for two captures, the status column with its filter, and empty-manifest rejection.
 */
#[Group('actions')]
final class IndexActionTest extends ActionTestCase
{
    public function testActionIndexPropagatesCursorFromQueryParam(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-index-cursor',
            [],
        );

        $_GET['cursor'] = 'tag-index-cursor';

        $html = $this->runDebugAction(
            new IndexAction('index'),
            $module,
        );

        self::assertNotSame(
            '',
            $html,
            'Index must still render when a cursor query param is present.',
        );
    }

    public function testActionIndexRendersComparisonFormWhenManifestHasTwoCaptures(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-index-older',
            [],
        );
        $this->writeDebugSnapshot(
            $module,
            'tag-index-newest',
            [],
        );

        $html = $this->runDebugAction(
            new IndexAction('index'),
            $module,
        );

        self::assertIsString(
            $html,
            'Index action must return rendered HTML.',
        );
        self::assertStringContainsString(
            'id="yii-debug-history-compare-title"',
            $html,
            'Two captures must expose the comparison form on the history page.',
        );
    }

    public function testActionIndexRendersStatusColumnWithFilterAndBadges(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-history-success',
            [],
            ['statusCode' => 200],
        );
        $this->writeDebugSnapshot(
            $module,
            'tag-history-error',
            [],
            ['statusCode' => 500],
        );

        $html = $this->runDebugAction(new IndexAction('index'), $module);

        self::assertIsString(
            $html,
            'Index action must return rendered HTML.',
        );
        self::assertStringContainsString(
            '<td class="yii-debug-col-status"><span class="yii-debug-badge yii-debug-status-2xx">200</span></td>',
            $html,
            'A 2xx row must render the success badge.',
        );
        self::assertStringContainsString(
            '<td class="yii-debug-col-status"><span class="yii-debug-badge yii-debug-status-5xx">500</span></td>',
            $html,
            'A 5xx row must render the server-error badge.',
        );

        Yii::$app->getRequest()->setQueryParams(['Debug' => ['statusCode' => '500']]);

        $html = $this->runDebugAction(new IndexAction('index'), $module);

        self::assertIsString(
            $html,
            'Filtered index action must return rendered HTML.',
        );
        self::assertStringContainsString(
            '<th scope="col"><a href="/index.php?r=debug%2Findex&amp;Debug%5BstatusCode%5D=500&amp;sort=method" '
            . 'data-sort="method">Method</a></th><th class="yii-debug-col-status" scope="col">'
            . '<a href="/index.php?r=debug%2Findex&amp;Debug%5BstatusCode%5D=500&amp;sort=statusCode" '
            . 'data-sort="statusCode">Status</a></th><th scope="col">AJAX</th>',
            $html,
            'Sortable Status header must sit between Method and AJAX.',
        );
        self::assertStringContainsString(
            '<td class="yii-debug-col-status"><input type="text" name="Debug[statusCode]" value="500" '
            . 'aria-label="Filter by Status"></td>',
            $html,
            'Status filter input must keep the submitted value.',
        );
        self::assertStringContainsString(
            'Debug%5BstatusCode%5D=200',
            $html,
            'The summary must retain the successful-response filter link.',
        );
        self::assertStringContainsString(
            'Debug%5BstatusCode%5D=500',
            $html,
            'The summary must retain the server-error filter link.',
        );
        self::assertStringContainsString(
            'data-yii-debug-tag="tag-history-error"',
            $html,
            'Status filtering must retain the matching history row.',
        );
        self::assertStringNotContainsString(
            'data-yii-debug-tag="tag-history-success"',
            $html,
            'Status filtering must exclude nonmatching history rows.',
        );
        self::assertStringContainsString(
            'yii-debug-active-filter-pill',
            $html,
            'The selected status must remain removable through the active-filter banner.',
        );
    }

    public function testActionIndexRendersWhenManifestIsPopulated(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-index',
            [
                'config' => ConfigSnapshot::capture(
                    [
                        'application' => ['yii' => 'captured-index-yii'],
                        'php' => ['version' => 'captured-index-php'],
                    ],
                ),
            ],
        );

        $html = $this->runDebugAction(
            new IndexAction('index'),
            $module,
        );

        self::assertNotSame(
            '',
            $html,
            'Rendered index must not be empty.',
        );

        $shell = Yii::$app->view->params['debugShell'] ?? null;

        self::assertInstanceOf(
            ShellContext::class,
            $shell,
            'Index must install the typed shell context.',
        );
        self::assertSame(
            'captured-index-php',
            $shell->phpVersion,
            'Index must load the latest entry before building its shell.',
        );
    }

    /**
     * @param class-string $class Library class exposing the stable entry point.
     * @param 'protected'|'public' $visibility Access required by consumers or subclasses.
     */
    #[TestWith([IndexAction::class, 'run', 'public'])]
    public function testExtensionEntryPointsRetainVisibility(string $class, string $method, string $visibility): void
    {
        $entry = new \ReflectionMethod($class, $method);

        self::assertTrue(
            $visibility === 'public' ? $entry->isPublic() : $entry->isProtected(),
            "{$class}::{$method} must retain {$visibility} access for consuming applications.",
        );
    }

    public function testThrowExceptionWhenIndexCalledOnEmptyManifest(): void
    {
        $module = $this->bootDebugModule();

        $action = new ViewAction('view');

        $action->setModule($module);


        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Message::DEBUG_DATA_EMPTY->getMessage(),
        );

        $this->runDebugAction(new IndexAction('index'), $module);
    }
}
