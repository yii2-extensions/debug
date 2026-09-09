<?php

declare(strict_types=1);

namespace yii\debug\tests\actions;

use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use PHPForge\Debug\Panel\Timeline\TimelineSnapshot;
use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\actions\ViewAction;
use yii\debug\Panel;
use yii\debug\panels\TimelinePanel;
use yii\debug\tests\support\ActionTestCase;

/**
 * Unit tests for {@see ViewAction} covering Timeline-to-Profiling aliases, explicit Timeline panel preservation,
 * Timeline filter normalization and precedence, and removal of obsolete view state and empty filter groups.
 */
#[Group('actions')]
final class ViewActionCompatibilityTest extends ActionTestCase
{
    public function testActionViewAliasesTimelineLinkToProfiling(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-view-timeline-alias',
            ['profiling' => new ProfilingSnapshot(1024, 0.001, [], [])],
        );

        Yii::$app->getRequest()
            ->setQueryParams(
                [
                    'tag' => 'tag-view-timeline-alias',
                    'panel' => 'timeline',
                    'view' => 'timeline',
                    'Timeline' => ['duration' => '5', 'category' => 'timeline-category'],
                    'Profile' => ['category' => 'current', 'info' => 'SELECT'],
                ],
            );

        $this->runDebugAction(
            new ViewAction('view'),
            $module,
            [
                'tag' => 'tag-view-timeline-alias',
                'panel' => 'timeline',
            ],
        );

        $profilingPanel = $module->panels['profiling'] ?? null;

        self::assertInstanceOf(
            Panel::class,
            $profilingPanel,
            'The Profiling panel must remain registered for a Timeline link.',
        );

        $context = $profilingPanel->getRenderContext();

        self::assertNotNull(
            $context,
            'A Timeline link must provide the unified Profiling render context.',
        );
        self::assertSame(
            'profiling',
            $context->panel,
            'A Timeline link must activate Profiling when no Timeline panel is configured.',
        );
        self::assertSame(
            [
                'tag' => 'tag-view-timeline-alias',
                'panel' => 'profiling',
                'Profile' => ['category' => 'current', 'info' => 'SELECT', 'duration' => '5'],
            ],
            $context->queryParams,
            'Timeline filters must map to Profile without overriding current filter values.',
        );
    }

    public function testActionViewDropsEmptyProfileFilterState(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-view-empty-profile-state',
            ['profiling' => new ProfilingSnapshot(1024, 0.001, [], [])],
        );

        Yii::$app->getRequest()
            ->setQueryParams(
                [
                    'tag' => 'tag-view-empty-profile-state',
                    'panel' => 'profiling',
                    'view' => 'timeline',
                    'Timeline' => [],
                    'Profile' => [],
                ],
            );

        $this->runDebugAction(
            new ViewAction('view'),
            $module,
            [
                'tag' => 'tag-view-empty-profile-state',
                'panel' => 'profiling',
            ],
        );

        $profilingPanel = $module->panels['profiling'] ?? null;

        self::assertInstanceOf(
            Panel::class,
            $profilingPanel,
            'The Profiling panel must remain registered while empty filter state is normalized.',
        );
        self::assertSame(
            [
                'tag' => 'tag-view-empty-profile-state',
                'panel' => 'profiling',
            ],
            $profilingPanel->getRenderContext()?->queryParams,
            'Profiling must remove obsolete view state and empty filter groups.',
        );
    }

    public function testActionViewKeepsExplicitTimelinePanelActive(): void
    {
        $module = $this->bootDebugModule();

        $timelinePanel = new TimelinePanel(['id' => 'timeline', 'module' => $module]);

        $module->panels['timeline'] = $timelinePanel;

        $start = 1_700_000_000.0;

        $this->writeDebugSnapshot(
            $module,
            'tag-view-explicit-timeline',
            [
                'profiling' => new ProfilingSnapshot(1024, 0.001, [], []),
                'timeline' => new TimelineSnapshot($start, $start + 0.001, 1024),
            ],
        );
        $this->runDebugAction(
            new ViewAction('view'),
            $module,
            [
                'tag' => 'tag-view-explicit-timeline',
                'panel' => 'timeline',
            ],
        );

        self::assertSame(
            'timeline',
            $timelinePanel->getRenderContext()?->panel,
            'An explicitly configured Timeline panel must continue to handle its own links.',
        );
    }

    public function testActionViewNormalizesTimelineQueryStateOnProfiling(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-view-profiling-timeline-state',
            ['profiling' => new ProfilingSnapshot(1024, 0.001, [], [])],
        );

        Yii::$app->getRequest()
            ->setQueryParams(
                [
                    'tag' => 'tag-view-profiling-timeline-state',
                    'panel' => 'profiling',
                    'view' => 'timeline',
                    'Timeline' => ['duration' => ['nested'], 'category' => 'timeline-category'],
                    'Profile' => ['duration' => '7', 'category' => ['nested'], 'info' => 'SELECT'],
                ],
            );

        $this->runDebugAction(
            new ViewAction('view'),
            $module,
            [
                'tag' => 'tag-view-profiling-timeline-state',
                'panel' => 'profiling',
            ],
        );

        $profilingPanel = $module->panels['profiling'] ?? null;

        self::assertInstanceOf(
            Panel::class,
            $profilingPanel,
            'The Profiling panel must remain registered while Timeline query state is normalized.',
        );
        self::assertSame(
            [
                'tag' => 'tag-view-profiling-timeline-state',
                'panel' => 'profiling',
                'Profile' => ['duration' => '7', 'info' => 'SELECT', 'category' => 'timeline-category'],
            ],
            $profilingPanel->getRenderContext()?->queryParams,
            'Profiling must discard obsolete view state, preserve scalar Profile values, and map scalar Timeline filters.',
        );
    }
}
