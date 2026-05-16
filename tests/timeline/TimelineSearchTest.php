<?php

declare(strict_types=1);

namespace yii\debug\tests\timeline;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\{LogTarget, Module};
use yii\debug\models\search\TimelineSearch;
use yii\debug\panels\{ProfilingPanel, TimelinePanel};
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
        $firstRule = (new TimelineSearch())->rules()[0] ?? null;

        self::assertIsArray(
            $firstRule,
            'First rule must be a configuration tuple.'
        );
        self::assertSame(
            'safe',
            $firstRule[1] ?? null,
            "First rule must mark filter fields as 'safe'."
        );
    }

    public function testSearchAppliesDurationThresholdMatcher(): void
    {
        $panel = $this->makeTimelinePanel();

        $provider = (new TimelineSearch())->search(['TimelineSearch' => ['duration' => '40']], $panel);

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

        $provider = (new TimelineSearch())->search(['TimelineSearch' => ['category' => 'db']], $panel);

        self::assertCount(
            2,
            $provider->getModels(),
            "Substring match on 'db' must keep both 'app\\\\db' spans.",
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

        $provider = $search->search(['TimelineSearch' => ['category' => 'db']], $panel);

        self::assertCount(
            2,
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

        $profiling->data = [
            'time' => 0.1,
            'messages' => [
                ['t1', Logger::LEVEL_PROFILE_BEGIN, 'app\\db', 1_700_000_000.000, [], 1024],
                ['t1', Logger::LEVEL_PROFILE_END, 'app\\db', 1_700_000_000.010, [], 2048],
                ['t2', Logger::LEVEL_PROFILE_BEGIN, 'app\\db', 1_700_000_000.020, [], 2048],
                ['t2', Logger::LEVEL_PROFILE_END, 'app\\db', 1_700_000_000.080, [], 4096],
            ],
        ];

        $module->panels['profiling'] = $profiling;

        $panel = new TimelinePanel(['id' => 'timeline', 'module' => $module]);

        $panel->load(['start' => 1_700_000_000.0, 'end' => 1_700_000_000.1, 'memory' => 1024]);

        return $panel;
    }
}
