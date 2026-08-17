<?php

declare(strict_types=1);

namespace yii\debug\tests\queue;

use PHPForge\Debug\Panel\Queue\JobRecord;
use PHPUnit\Framework\Attributes\Group;
use yii\data\Sort;
use yii\debug\models\search\QueueSearch;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see QueueSearch} covering filter validation, pagination metadata and the substring/exact match
 * dispatch backing the Queue panel filter form.
 */
#[Group('panel')]
#[Group('queue')]
final class QueueSearchTest extends TestCase
{
    public function testAttributeLabelsCoverEveryFilterField(): void
    {
        $labels = (new QueueSearch())->attributeLabels();

        self::assertArrayHasKey(
            'eventType',
            $labels,
            "'eventType' label must be defined.",
        );
        self::assertArrayHasKey(
            'driverName',
            $labels,
            "'driverName' label must be defined.",
        );
        self::assertArrayHasKey(
            'componentId',
            $labels,
            "'componentId' label must be defined.",
        );
        self::assertArrayHasKey(
            'jobClass',
            $labels,
            "'jobClass' label must be defined.",
        );
        self::assertArrayHasKey(
            'jobId',
            $labels,
            "'jobId' label must be defined.",
        );
    }

    public function testSearchAppliesPartialMatchOnDriverName(): void
    {
        $records = [
            JobRecord::fromCapture(
                [
                    'eventType' => 'push',
                    'componentId' => 'queue',
                    'driverName' => 'Sync',
                    'jobClass' => 'A',
                    'time' => 1.0,
                ],
            ),
            JobRecord::fromCapture(
                [
                    'eventType' => 'push',
                    'componentId' => 'queueRedis',
                    'driverName' => 'Redis',
                    'jobClass' => 'B',
                    'time' => 2.0,
                ],
            ),
            JobRecord::fromCapture(
                [
                    'eventType' => 'push',
                    'componentId' => 'queueRabbit',
                    'driverName' => 'AMQP',
                    'jobClass' => 'C',
                    'time' => 3.0,
                ],
            ),
        ];

        $searchModel = new QueueSearch();

        $dataProvider = $searchModel->search(
            ['QueueSearch' => ['driverName' => 'Re']],
            $records,
        );

        self::assertCount(
            1,
            $dataProvider->getModels(),
            "Substring 'Re' must match 'Redis' only.",
        );
        self::assertSame(
            1,
            $dataProvider->getTotalCount(),
            'Total must reflect the filtered set.',
        );
    }

    public function testSearchAppliesPartialMatchOnJobClass(): void
    {
        $records = [
            JobRecord::fromCapture(
                [
                    'eventType' => 'push',
                    'componentId' => 'queue',
                    'driverName' => 'Sync',
                    'jobClass' => 'app\\jobs\\HelloJob',
                    'time' => 1.0,
                ],
            ),
            JobRecord::fromCapture(
                [
                    'eventType' => 'push',
                    'componentId' => 'queue',
                    'driverName' => 'Sync',
                    'jobClass' => 'app\\jobs\\OrderJob',
                    'time' => 2.0,
                ],
            ),
            JobRecord::fromCapture(
                [
                    'eventType' => 'push',
                    'componentId' => 'queue',
                    'driverName' => 'Sync',
                    'jobClass' => 'app\\jobs\\EmailJob',
                    'time' => 3.0,
                ],
            ),
        ];

        $searchModel = new QueueSearch();

        $dataProvider = $searchModel->search(
            ['QueueSearch' => ['jobClass' => 'Hello']],
            $records,
        );

        self::assertCount(
            1,
            $dataProvider->getModels(),
            "Substring 'Hello' must match the 'HelloJob' only.",
        );
    }

    public function testSearchAppliesPartialMatchOnJobId(): void
    {
        $records = [
            JobRecord::fromCapture(
                [
                    'eventType' => 'push',
                    'componentId' => 'queue',
                    'driverName' => 'Sync',
                    'jobClass' => 'A',
                    'jobId' => '101',
                    'time' => 1.0,
                ],
            ),
            JobRecord::fromCapture(
                [
                    'eventType' => 'push',
                    'componentId' => 'queue',
                    'driverName' => 'Sync',
                    'jobClass' => 'A',
                    'jobId' => '202',
                    'time' => 2.0,
                ],
            ),
            JobRecord::fromCapture(
                [
                    'eventType' => 'push',
                    'componentId' => 'queue',
                    'driverName' => 'Sync',
                    'jobClass' => 'A',
                    'jobId' => '303',
                    'time' => 3.0,
                ],
            ),
        ];

        $searchModel = new QueueSearch();

        $dataProvider = $searchModel->search(
            ['QueueSearch' => ['jobId' => '20']],
            $records,
        );

        self::assertCount(
            1,
            $dataProvider->getModels(),
            "Substring '20' must match the '202' job id only.",
        );
    }

    public function testSearchCombinesMultipleFilters(): void
    {
        $records = [
            JobRecord::fromCapture(
                [
                    'eventType' => 'push',
                    'componentId' => 'queue',
                    'driverName' => 'Sync',
                    'jobClass' => 'A',
                    'time' => 1.0,
                ],
            ),
            JobRecord::fromCapture(
                [
                    'eventType' => 'exec',
                    'componentId' => 'queue',
                    'driverName' => 'Sync',
                    'jobClass' => 'A',
                    'time' => 2.0,
                ],
            ),
            JobRecord::fromCapture(
                [
                    'eventType' => 'error',
                    'componentId' => 'queue',
                    'driverName' => 'Sync',
                    'jobClass' => 'A',
                    'time' => 4.0,
                ],
            ),
        ];

        $searchModel = new QueueSearch();

        $dataProvider = $searchModel->search(
            [
                'QueueSearch' => ['eventType' => 'push', 'componentId' => 'queue'],
            ],
            $records,
        );

        self::assertCount(
            1,
            $dataProvider->getModels(),
            'Only one record satisfies push + queue.',
        );
    }

    public function testSearchDoesNotApplyPreexistingAttributesWhenParametersAreEmpty(): void
    {
        $records = [
            JobRecord::fromCapture(
                [
                    'eventType' => 'push',
                    'componentId' => 'queue',
                    'driverName' => 'Sync',
                    'jobClass' => 'A',
                    'time' => 1.0,
                ],
            ),
            JobRecord::fromCapture(
                [
                    'eventType' => 'error',
                    'componentId' => 'queue',
                    'driverName' => 'Sync',
                    'jobClass' => 'B',
                    'time' => 2.0,
                ],
            ),
        ];

        $searchModel = new QueueSearch();

        $searchModel->eventType = 'error';

        $dataProvider = $searchModel->search([], $records);

        self::assertSame(
            2,
            $dataProvider->getTotalCount(),
            'An unsubmitted filter form must keep the full captured set.',
        );
    }

    public function testSearchExposesDefaultPageSizeOfTwentyFive(): void
    {
        $searchModel = new QueueSearch();

        $dataProvider = $searchModel->search(
            [],
            [],
        );

        $pagination = $dataProvider->getPagination();

        self::assertNotFalse(
            $pagination,
            'Pagination must be initialized on the data provider.',
        );
        self::assertSame(
            25,
            $pagination->pageSize,
            "Default page size must be '25'.",
        );

        $sort = $dataProvider->getSort();

        self::assertInstanceOf(
            Sort::class,
            $sort,
            'Sorting must be initialized on the data provider.',
        );
        self::assertSame(
            ['eventType', 'driverName', 'componentId', 'jobClass', 'jobId', 'time', 'duration'],
            array_keys($sort->attributes),
            'Every displayed queue field must remain sortable.',
        );
        self::assertSame(
            ['time' => SORT_ASC],
            $sort->defaultOrder,
            'Queue records must sort chronologically by default.',
        );
    }

    public function testSearchFiltersByComponentIdExactMatch(): void
    {
        $records = [
            JobRecord::fromCapture(
                [
                    'eventType' => 'push',
                    'componentId' => 'queue',
                    'driverName' => 'Sync',
                    'jobClass' => 'A',
                    'time' => 1.0,
                ],
            ),
            JobRecord::fromCapture(
                [
                    'eventType' => 'push',
                    'componentId' => 'queueRedis',
                    'driverName' => 'Redis',
                    'jobClass' => 'B',
                    'time' => 2.0,
                ],
            ),
        ];

        $dataProvider = (new QueueSearch())->search(
            ['QueueSearch' => ['componentId' => 'queueRedis']],
            $records,
        );

        $models = $dataProvider->getModels();

        self::assertCount(
            1,
            $models,
            'The component filter must keep one queue component.',
        );

        $first = $models[0] ?? null;

        self::assertInstanceOf(
            JobRecord::class,
            $first,
            'The filtered model must remain a queue record.',
        );
        self::assertSame(
            'queueRedis',
            $first->componentId,
            'The exact queue component must be retained.',
        );
    }

    public function testSearchFiltersByEventTypeExactMatch(): void
    {
        $records = [
            JobRecord::fromCapture(
                [
                    'eventType' => 'push',
                    'componentId' => 'queue',
                    'driverName' => 'Sync',
                    'jobClass' => 'A',
                    'time' => 1.0,
                ],
            ),
            JobRecord::fromCapture(
                [
                    'eventType' => 'exec',
                    'componentId' => 'queue',
                    'driverName' => 'Sync',
                    'jobClass' => 'A',
                    'time' => 2.0,
                ],
            ),
            JobRecord::fromCapture(
                [
                    'eventType' => 'error',
                    'componentId' => 'queue',
                    'driverName' => 'Sync',
                    'jobClass' => 'B',
                    'time' => 3.0,
                ],
            ),
        ];

        $searchModel = new QueueSearch();

        $dataProvider = $searchModel->search(
            ['QueueSearch' => ['eventType' => 'error']],
            $records
        );

        self::assertCount(
            1,
            $dataProvider->getModels(),
            "Only the 'error' record must remain.",
        );
    }

    public function testSearchPaginatesWhenRecordCountExceedsPageSize(): void
    {
        $records = [];

        for ($i = 1; $i <= 60; $i++) {
            $records[] = JobRecord::fromCapture(
                [
                    'eventType' => 'push',
                    'componentId' => 'queue',
                    'driverName' => 'Sync',
                    'jobClass' => 'app\\jobs\\HelloJob',
                    'time' => (float) $i,
                ],
            );
        }

        $searchModel = new QueueSearch();

        $dataProvider = $searchModel->search(
            [],
            $records,
        );

        $pagination = $dataProvider->getPagination();

        self::assertNotFalse(
            $pagination,
            'Pagination must be initialized on the data provider.',
        );
        self::assertSame(
            60,
            $dataProvider->getTotalCount(),
            'Total must include every record.',
        );
        self::assertCount(
            25,
            $dataProvider->getModels(),
            'A single page must hold the page-size cap.',
        );
        self::assertSame(
            3,
            $pagination->getPageCount(),
            "Sixty records / '25' per page = three pages.",
        );
    }

    public function testSearchReturnsAllRecordsWhenFilterIsEmpty(): void
    {
        $records = [
            JobRecord::fromCapture(
                [
                    'eventType' => 'push',
                    'componentId' => 'queue',
                    'driverName' => 'Sync',
                    'jobClass' => 'A',
                    'time' => 1.0,
                ],
            ),
            JobRecord::fromCapture(
                [
                    'eventType' => 'exec',
                    'componentId' => 'queue',
                    'driverName' => 'Sync',
                    'jobClass' => 'B',
                    'time' => 2.0,
                ],
            ),
        ];

        $searchModel = new QueueSearch();

        $dataProvider = $searchModel->search(
            [],
            $records,
        );

        self::assertSame(
            2,
            $dataProvider->getTotalCount(),
            'No filter must keep the full set.',
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockWebApplication();
    }

    protected function tearDown(): void
    {
        $this->destroyApplication();

        parent::tearDown();
    }
}
