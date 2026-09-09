<?php

declare(strict_types=1);

namespace yii\debug\tests\actions;

use PHPUnit\Framework\Attributes\Group;
use Xepozz\InternalMocker\MockerState;
use Yii;
use yii\debug\actions\Action;
use yii\debug\actions\ViewAction;
use yii\debug\exception\Message;
use yii\debug\LogTarget;
use yii\debug\tests\support\ActionTestCase;
use yii\web\NotFoundHttpException;

use function file_put_contents;
use function mkdir;

/**
 * Unit tests for {@see Action} covering `getManifest` caching and forced reloads, `loadData` summary hydration,
 * missing-summary rejection, the default no-retry behavior, and manifest reloads and failures after explicit retries.
 */
#[Group('actions')]
final class ActionDataLoadingTest extends ActionTestCase
{
    public function testGetManifestCachesResultAndReloadsOnForce(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-first',
            [],
        );

        $action = new ViewAction('view');

        $action->setModule($module);

        $first = $action->getManifest();

        self::assertArrayHasKey(
            'tag-first',
            $first,
            "Initial manifest must include 'tag-first'.",
        );

        $this->writeDebugSnapshot(
            $module,
            'tag-second',
            [],
        );

        // Without forceReload the cached manifest must persist.
        $cached = $action->getManifest();

        self::assertArrayNotHasKey(
            'tag-second',
            $cached,
            'Cached manifest must not see new tags.',
        );

        $reloaded = $action->getManifest(true);

        self::assertArrayHasKey(
            'tag-second',
            $reloaded,
            'Forced reload must surface freshly written tags.',
        );
    }

    public function testLoadDataDoesNotRetryByDefault(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-other',
            [],
        );

        MockerState::addCondition(
            'yii\debug\actions',
            'sleep',
            [],
            0,
            true,
        );

        $action = new ViewAction('view');

        $action->setModule($module);

        try {
            $action->loadData('tag-missing');

            self::fail(
                'Missing tag must throw after the initial attempt.',
            );
        } catch (NotFoundHttpException $exception) {
            self::assertSame(
                Message::DEBUG_DATA_NOT_FOUND->getMessage('tag-missing'),
                $exception->getMessage(),
                'Missing tag must report the requested identifier.',
            );
        }

        self::assertSame(
            [],
            MockerState::getTraces('yii\debug\actions', 'sleep'),
            'Default load must not wait or retry.',
        );
    }

    public function testLoadDataPopulatesSummaryWhenTagIsKnown(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-load',
            [],
        );

        $action = new ViewAction('view');

        $action->setModule($module);
        $action->loadData('tag-load');

        self::assertSame(
            'tag-load',
            $action->summary?->tag,
            'Loaded summary must echo the active tag.',
        );
    }

    public function testLoadDataReloadsManifestAfterWaitingForLateTag(): void
    {
        $module = $this->bootDebugModule();

        $action = new ViewAction('view');

        $action->setModule($module);

        MockerState::addCondition(
            'yii\debug\actions',
            'sleep',
            [1],
            function (int $seconds) use ($module): int {
                self::assertSame(
                    1,
                    $seconds,
                    'Retry wait must receive the documented interval.',
                );

                $this->writeDebugSnapshot(
                    $module,
                    'tag-late',
                    [],
                );

                return 0;
            },
        );

        $action->loadData('tag-late', 1);

        self::assertSame(
            'tag-late',
            $action->summary?->tag,
            'Retry must reload the manifest and load a tag that appeared during the wait.',
        );
    }

    public function testThrowNotFoundHttpExceptionWhenLoadedTagLacksSummary(): void
    {
        $module = $this->bootDebugModule();

        $logTarget = $module->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            "'logTarget' must be wired by bootstrap.",
        );

        $dataPath = Yii::getAlias($module->dataPath);

        @mkdir($dataPath, 0o777, true);

        $tag = 'tag-no-summary';

        $this->writeDebugSnapshot(
            $module,
            $tag,
            [],
        );

        file_put_contents("{$dataPath}/{$tag}.json", '{"version":3,"panels":{},"failures":{}}');

        $action = new ViewAction('view');

        $action->setModule($module);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            Message::DEBUG_DATA_SUMMARY_MISSING->getMessage($tag),
        );

        $action->loadData($tag);
    }

    public function testThrowNotFoundHttpExceptionWhenTagIsNotFoundAfterRetries(): void
    {
        $module = $this->bootDebugModule();
        // Persist a different tag so the retry path runs but 'tag-rotated' never appears.
        $this->writeDebugSnapshot(
            $module,
            'tag-other',
            [],
        );

        $action = new ViewAction('view');

        $action->setModule($module);

        MockerState::addCondition(
            'yii\debug\actions',
            'sleep',
            [],
            0,
            true,
        );

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            Message::DEBUG_DATA_NOT_FOUND->getMessage('tag-rotated'),
        );

        try {
            $action->loadData('tag-rotated', 1);

            self::fail(
                'Missing tag must throw after the configured retry.',
            );
        } catch (NotFoundHttpException $exception) {
            self::assertSame(
                Message::DEBUG_DATA_NOT_FOUND->getMessage('tag-rotated'),
                $exception->getMessage(),
                'Missing tag must report the requested identifier.',
            );
            self::assertSame(
                [[1]],
                array_column(MockerState::getTraces('yii\debug\actions', 'sleep'), 'arguments'),
                'One retry must wait exactly once for one second between the two attempts.',
            );

            throw $exception;
        }
    }
}
