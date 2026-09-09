<?php

declare(strict_types=1);

namespace yii\debug\tests\actions;

use Exception;
use PHPForge\Debug\Panel\Config\ConfigSnapshot;
use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPForge\Debug\Storage\RequestSummary;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Yii;
use yii\debug\actions\ViewAction;
use yii\debug\exception\Message;
use yii\debug\Panel;
use yii\debug\panels\RequestSummaryAwarePanelInterface;
use yii\debug\tests\support\ActionTestCase;
use yii\debug\widgets\shell\ShellContext;
use yii\web\NotFoundHttpException;

/**
 * Unit tests for {@see ViewAction} covering capture selection when the tag is `null`, explicit panel rendering and
 * shell/render contexts, request-summary injection, panel-error rendering with HTTP 500, and empty-manifest rejection.
 */
#[Group('actions')]
final class ViewActionTest extends ActionTestCase
{
    public function testActionViewFallsBackToFirstTagWhenTagIsNull(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-view-first',
            ['log' => LogSnapshot::capture([])],
        );

        $html = $this->runDebugAction(
            new ViewAction('view'),
            $module,
        );

        self::assertNotSame(
            '',
            $html,
            "'view' must render the most recent tag when none is given.",
        );
    }

    public function testActionViewInjectsLoadedSummaryIntoAwarePanel(): void
    {
        $module = $this->bootDebugModule();

        $awarePanel = new class extends Panel implements RequestSummaryAwarePanelInterface {
            public RequestSummary|null $receivedSummary = null;

            public function getDetail(): string
            {
                return 'Summary-aware panel';
            }

            public function hydrate(array $payload): void {}

            public function setRequestSummary(RequestSummary $summary): void
            {
                $this->receivedSummary = $summary;
            }
        };

        $awarePanel->id = 'summary-aware';
        $awarePanel->module = $module;
        $module->panels = ['summary-aware' => $awarePanel];

        $this->writeDebugSnapshot(
            $module,
            'tag-view-summary-aware',
            ['summary-aware' => ConfigSnapshot::capture([])],
        );
        $this->runDebugAction(
            new ViewAction('view'),
            $module,
            [
                'tag' => 'tag-view-summary-aware',
                'panel' => 'summary-aware',
            ],
        );

        self::assertSame(
            'tag-view-summary-aware',
            $awarePanel->receivedSummary?->tag,
            'The active summary-aware panel must receive the loaded request summary before rendering.',
        );
    }

    public function testActionViewRendersExplicitPanel(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-view-panel',
            ['request' => RequestSnapshot::capture(['statusCode' => 200])],
        );

        $html = $this->runDebugAction(
            new ViewAction('view'),
            $module,
            [
                'tag' => 'tag-view-panel',
                'panel' => 'request',
            ],
        );

        self::assertNotSame(
            '',
            $html,
            "Explicit panel id must render the panel's view.",
        );

        $shell = Yii::$app->view->params['debugShell'] ?? null;

        self::assertInstanceOf(
            ShellContext::class,
            $shell,
            'Explicit panel view must install a typed shell context.',
        );
        self::assertSame(
            ShellContext::MODE_VIEW,
            $shell->mode,
            'Explicit panel view must set the shell context to view mode.',
        );
        self::assertTrue(
            $shell->useShell,
            'Explicit panel view must render the full debug shell.',
        );

        $requestPanel = $module->panels['request'] ?? null;

        self::assertInstanceOf(
            Panel::class,
            $requestPanel,
            'Request panel must remain registered after the snapshot is rendered.',
        );

        $context = $requestPanel->getRenderContext();

        self::assertInstanceOf(
            PanelRenderContext::class,
            $context,
            'Explicit panel views must install the portable panel render context.',
        );
        self::assertSame(
            'tag-view-panel',
            $context->tag,
            'Portable render context must target the loaded capture.',
        );
        self::assertStringContainsString(
            'panel=request',
            $context->panelUrl(queryParams: []),
            'Portable render context must delegate panel links to the Yii URL generator.',
        );
    }

    public function testActionViewRendersPanelExceptionWhenPanelReportsError(): void
    {
        $module = $this->bootDebugModule();

        // Persist an exception on the 'request' panel via the failure channel of the snapshot.
        $error = new RuntimeException('Boom');

        $this->writeDebugSnapshot(
            $module,
            'tag-view-error',
            [],
            failures: ['request' => $error],
        );

        $html = $this->runDebugAction(
            new ViewAction('view'),
            $module,
            [
                'tag' => 'tag-view-error',
                'panel' => 'request',
            ],
        );

        self::assertSame(
            500,
            Yii::$app->response->getStatusCode(),
            'Panel error must surface a 500 status code.',
        );
        self::assertNotSame(
            '',
            $html,
            'Exception view must render markup.',
        );
    }

    public function testThrowNotFoundHttpExceptionWhenManifestIsEmptyForView(): void
    {
        $module = $this->bootDebugModule();

        $action = new ViewAction('view');

        $action->setModule($module);


        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            Message::DEBUG_DATA_EMPTY->getMessage(),
        );

        $this->runDebugAction(
            new ViewAction('view'),
            $module,
        );
    }
}
