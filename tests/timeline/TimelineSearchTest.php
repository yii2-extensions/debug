<?php

declare(strict_types=1);

namespace yii\debug\tests\timeline;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\data\Sort;
use yii\debug\{LogTarget, Module};
use yii\debug\models\search\TimelineSearch;
use yii\debug\panels\profile\ProfilingSnapshot;
use yii\debug\panels\{ProfilingPanel, TimelinePanel};
use yii\debug\panels\timeline\TimelineSnapshot;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;
use yii\web\Controller;

/**
 * Unit tests for {@see TimelineSearch} covering attribute labels, validation rules, the category substring filter,
 * and the duration-threshold matcher backing the Timeline panel grid.
 */
#[Group('timeline')]
#[Group('search')]
final class TimelineSearchTest extends TestCase
{
    public function testAttributeLabelsLabelTheDurationFilter(): void
    {
        $labels = (new TimelineSearch())->attributeLabels();

        self::assertArrayHasKey(
            'duration',
            $labels,
            "'duration' label must be defined.",
        );
        self::assertSame(
            'Duration ≥',
            $labels['duration'],
            'Duration label must surface the threshold semantics.',
        );
    }

    public function testRulesMarkEveryFilterAsSafe(): void
    {
        self::assertSame(
            [[['category', 'duration'], 'safe']],
            (new TimelineSearch())->rules(),
            "First rule must mark filter fields as 'safe'."
        );
    }

    public function testSearchAppliesDurationThresholdMatcher(): void
    {
        $panel = $this->makeTimelinePanel();

        $provider = (new TimelineSearch())
            ->search(['TimelineSearch' => ['duration' => '40']], $panel);

        $models = $provider->getModels();

        self::assertCount(
            1,
            $models,
            "Duration threshold of '40 ms' must drop the '10 ms' span.",
        );
    }

    public function testSearchAppliesPartialMatchOnCategory(): void
    {
        $panel = $this->makeTimelinePanel();

        $provider = (new TimelineSearch())
            ->search(['TimelineSearch' => ['category' => 'db']], $panel);

        self::assertCount(
            2,
            $provider->getModels(),
            "Substring match on 'db' must keep both 'app\\\\db' spans.",
        );
    }

    public function testSearchConfiguresTimelineSortingContract(): void
    {
        $sort = (new TimelineSearch())
            ->search([], $this->makeTimelinePanel())->getSort();

        self::assertInstanceOf(
            Sort::class,
            $sort,
            'Timeline sorting must be enabled.',
        );
        self::assertSame(
            ['category', 'timestamp'],
            array_keys($sort->attributes),
            'Timeline sorting must retain category and timestamp.',
        );
    }

    public function testSearchReturnsUnfilteredProviderWhenValidateShortCircuits(): void
    {
        $panel = $this->makeTimelinePanel();

        $search = new class extends TimelineSearch {
            public function beforeValidate(): bool
            {
                return false;
            }

            public function formName(): string
            {
                return 'TimelineSearch';
            }
        };

        $provider = $search
            ->search(['TimelineSearch' => ['category' => 'missing']], $panel);

        self::assertCount(
            3,
            $provider->getModels(),
            'Failed validation must short-circuit filtering and keep every captured span.',
        );
    }

    private function makeTimelinePanel(): TimelinePanel
    {
        $assetPath = dirname(__DIR__, 2) . '/runtime/assets';

        @mkdir($assetPath, 0o777, true);

        $this->mockWebApplication(
            [
                'components' => [
                    'assetManager' => [
                        'basePath' => $assetPath,
                        'baseUrl' => '/assets',
                    ],
                ],
            ],
        );

        $module = new Module('debug');
        $module->logTarget = new LogTarget($module);

        Yii::$app->controller = new Controller('debug', $module);

        $profiling = new ProfilingPanel(['id' => 'profiling', 'module' => $module]);

        $this->hydratePanel(
            $profiling,
            ProfilingSnapshot::capture(
                0,
                0.1,
                [
                    ['t1', Logger::LEVEL_PROFILE_BEGIN, 'app\\db', 1_700_000_000.000, [], 1024],
                    ['t1', Logger::LEVEL_PROFILE_END, 'app\\db', 1_700_000_000.010, [], 2048],
                    ['t2', Logger::LEVEL_PROFILE_BEGIN, 'app\\db', 1_700_000_000.020, [], 2048],
                    ['t2', Logger::LEVEL_PROFILE_END, 'app\\db', 1_700_000_000.080, [], 4096],
                    ['t3', Logger::LEVEL_PROFILE_BEGIN, 'app\\view', 1_700_000_000.085, [], 4096],
                    ['t3', Logger::LEVEL_PROFILE_END, 'app\\view', 1_700_000_000.090, [], 4096],
                ],
            ),
        );

        $module->panels['profiling'] = $profiling;

        $panel = new TimelinePanel(['id' => 'timeline', 'module' => $module]);

        $this->hydratePanel(
            $panel,
            new TimelineSnapshot(1_700_000_000.0, 1_700_000_000.1, 1024),
        );

        return $panel;
    }
}
