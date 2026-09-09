<?php

declare(strict_types=1);

namespace yii\debug\tests\actions;

use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPForge\Debug\Storage\PanelSnapshot;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Yii;
use yii\debug\actions\CompareAction;
use yii\debug\exception\Message;
use yii\debug\{Module, Panel};
use yii\debug\tests\support\ActionTestCase;
use yii\debug\widgets\shell\ShellContext;
use yii\web\NotFoundHttpException;

use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function json_encode;
use function unlink;

use const JSON_THROW_ON_ERROR;

/**
 * Unit tests for {@see CompareAction} covering default and explicit capture selection, target loading and index-shell
 * setup, summary and structural panel differences, failed-panel rendering, and missing or insufficient captures.
 */
#[Group('actions')]
final class CompareActionTest extends ActionTestCase
{
    public function testActionCompareKeepsExplicitBaselineWhenTargetOmitted(): void
    {
        $module = $this->bootModuleWithComparePair();

        $html = $this->runDebugAction(
            new CompareAction('compare'),
            $module,
            ['baseline' => 'tag-compare-newest'],
        );

        self::assertIsString(
            $html,
            'Comparison action must return rendered HTML.',
        );
        self::assertStringNotContainsString(
            '+4 (',
            $html,
            'Explicit baseline must not be replaced by the default.',
        );
    }

    public function testActionCompareLoadsTargetDataAndPreparesIndexShell(): void
    {
        $module = $this->bootModuleWithComparePair(
            ['request' => RequestSnapshot::capture(['statusCode' => 200, 'method' => 'GET'])],
        );

        $this->runDebugAction(
            new CompareAction('compare'),
            $module,
            [],
        );

        $shell = Yii::$app->getView()->params['debugShell'] ?? null;

        self::assertInstanceOf(
            ShellContext::class,
            $shell,
            'Shell context must be installed on the view.',
        );
        self::assertSame(
            ShellContext::MODE_INDEX,
            $shell->mode,
            'Shell must use the index mode.',
        );
        self::assertNotNull(
            $shell->sidebar,
            'Index shell must carry the sidebar payload.',
        );

        $requestPanel = $module->panels['request'] ?? null;

        self::assertInstanceOf(
            Panel::class,
            $requestPanel,
            'Request panel must stay registered.',
        );
        self::assertSame(
            'tag-compare-newest',
            $requestPanel->tag,
            'Target data must be loaded into the panels.',
        );
    }

    public function testActionCompareRejectsManifestEntryWhoseSnapshotFileIsMissing(): void
    {
        $module = $this->bootModuleWithComparePair();

        $snapshotFile = "{$module->dataPath}/tag-compare-older.json";

        self::assertTrue(
            unlink($snapshotFile),
            'Baseline snapshot fixture must be removable.',
        );
        self::assertFileDoesNotExist(
            $snapshotFile,
            'Baseline must remain in the manifest without its snapshot file.',
        );

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            Message::DEBUG_DATA_NOT_FOUND->getMessage('tag-compare-older'),
        );

        $this->runDebugAction(
            new CompareAction('compare'),
            $module,
            [
                'baseline' => 'tag-compare-older',
                'target' => 'tag-compare-newest',
            ],
        );
    }

    public function testActionCompareRejectsSnapshotMissingFromTheManifest(): void
    {
        $module = $this->bootModuleWithComparePair();

        $indexFile = "{$module->dataPath}/index.json";

        $manifest = json_decode((string) file_get_contents($indexFile), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray(
            $manifest,
            'Stored manifest must decode to an array.',
        );
        self::assertIsArray(
            $manifest['entries'] ?? null,
            'Stored manifest must contain entries.',
        );

        unset($manifest['entries']['tag-compare-older']);
        file_put_contents($indexFile, json_encode($manifest, JSON_THROW_ON_ERROR));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            Message::DEBUG_DATA_NOT_FOUND->getMessage('tag-compare-older'),
        );

        $this->runDebugAction(
            new CompareAction('compare'),
            $module,
            [
                'baseline' => 'tag-compare-older',
                'target' => 'tag-compare-newest',
            ],
        );
    }

    public function testActionCompareRejectsUnknownBaselineTag(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-compare-known',
            [],
        );

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            Message::DEBUG_DATA_NOT_FOUND->getMessage('tag-compare-absent'),
        );

        $this->runDebugAction(
            new CompareAction('compare'),
            $module,
            [
                'baseline' => 'tag-compare-absent',
                'target' => 'tag-compare-known',
            ],
        );
    }

    public function testActionCompareRejectsUnknownTag(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-compare-known',
            [],
        );

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            Message::DEBUG_DATA_NOT_FOUND->getMessage('tag-compare-missing'),
        );

        $this->runDebugAction(
            new CompareAction('compare'),
            $module,
            [
                'baseline' => 'tag-compare-known',
                'target' => 'tag-compare-missing',
            ],
        );
    }

    public function testActionCompareRendersFailedPanelState(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-compare-baseline',
            ['log' => LogSnapshot::capture([])],
        );
        $this->writeDebugSnapshot(
            $module,
            'tag-compare-target',
            [],
            failures: ['log' => new RuntimeException('Log capture failed.')],
        );

        $html = $this->runDebugAction(
            new CompareAction('compare'),
            $module,
            [
                'baseline' => 'tag-compare-baseline',
                'target' => 'tag-compare-target',
            ],
        );

        self::assertIsString(
            $html,
            'Comparison action must return rendered HTML.',
        );
        self::assertStringContainsString(
            'yii-debug-badge-danger">Failed</span>',
            $html,
            'A failed panel capture must use the danger state badge.',
        );
    }

    public function testActionCompareRendersSummaryAndStructuralPanelDifferences(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-compare-baseline',
            ['request' => RequestSnapshot::capture(['statusCode' => 200, 'method' => 'GET'])],
            [
                'processingTime' => 0.010,
                'peakMemory' => 1_048_576,
                'sqlCount' => 1,
            ],
        );
        $this->writeDebugSnapshot(
            $module,
            'tag-compare-target',
            ['request' => RequestSnapshot::capture(['statusCode' => 500, 'method' => 'POST'])],
            [
                'processingTime' => 0.015,
                'peakMemory' => 2_097_152,
                'sqlCount' => 3,
                'statusCode' => 500,
            ],
        );

        $html = $this->runDebugAction(
            new CompareAction('compare'),
            $module,
            [
                'baseline' => 'tag-compare-baseline',
                'target' => 'tag-compare-target',
            ],
        );

        self::assertIsString(
            $html,
            'Comparison action must return rendered HTML.',
        );
        self::assertStringContainsString(
            'Compare captures',
            $html,
            'Comparison page must expose its primary heading and form.',
        );
        self::assertStringContainsString(
            'Request metrics',
            $html,
            'Comparison page must expose canonical summary differences.',
        );
        self::assertStringContainsString(
            'Panel structure',
            $html,
            'Comparison page must expose privacy-preserving panel structural differences.',
        );
        self::assertStringContainsString(
            '<th scope="row">Request</th>',
            $html,
            'Panel rows must use the display name, not the panel ID.',
        );
        self::assertStringContainsString(
            '+5.00 ms (+50.0%)',
            $html,
            'Duration delta must be computed relative to the baseline.',
        );
    }

    public function testActionCompareUsesNewestCaptureAsTargetAndPreviousAsBaseline(): void
    {
        $module = $this->bootModuleWithComparePair();

        $html = $this->runDebugAction(
            new CompareAction('compare'),
            $module,
            []
        );

        self::assertIsString(
            $html,
            'Comparison action must return rendered HTML.',
        );
        self::assertStringContainsString(
            '+4 (',
            $html,
            'Delta must run from the older baseline to the newest target.',
        );
    }

    public function testThrowNotFoundHttpExceptionWhenSingleCaptureAndTargetOmitted(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-compare-only',
            [],
        );

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            Message::COMPARISON_CAPTURES_REQUIRED->getMessage(),
        );

        $this->runDebugAction(
            new CompareAction('compare'),
            $module,
            ['baseline' => 'tag-compare-only'],
        );
    }

    /**
     * Boots the debug module with an older/newest capture pair for the comparison tests.
     *
     * @param array<string, PanelSnapshot> $newestPanels Panel payloads stored with the newest capture.
     */
    private function bootModuleWithComparePair(array $newestPanels = []): Module
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-compare-older',
            [],
            ['sqlCount' => 1],
        );
        $this->writeDebugSnapshot(
            $module,
            'tag-compare-newest',
            $newestPanels,
            ['sqlCount' => 5],
        );

        return $module;
    }
}
