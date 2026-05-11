<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\models\search\Queue as QueueSearch;

/**
 * Unit tests for {@see QueueSearch} covering filter validation, pagination metadata and the substring/exact match
 * dispatch backing the Queue panel filter form.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('panel')]
#[Group('queue')]
final class QueueSearchTest extends TestCase
{
    public function testSearchAppliesPartialMatchOnDriverName(): void
    {
        $records = [
            [
                'eventType' => 'push',
                'componentId' => 'queue',
                'driverName' => 'Sync',
                'jobClass' => 'A',
                'time' => 1.0,
            ],
            [
                'eventType' => 'push',
                'componentId' => 'queueRedis',
                'driverName' => 'Redis',
                'jobClass' => 'B',
                'time' => 2.0,
            ],
            [
                'eventType' => 'push',
                'componentId' => 'queueRabbit',
                'driverName' => 'AMQP',
                'jobClass' => 'C',
                'time' => 3.0,
            ],
        ];

        $searchModel = new QueueSearch();

        $dataProvider = $searchModel->search(
            ['Queue' => ['driverName' => 'Re']],
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
            [
                'eventType' => 'push',
                'componentId' => 'queue',
                'driverName' => 'Sync',
                'jobClass' => 'app\\jobs\\HelloJob',
                'time' => 1.0,
            ],
            [
                'eventType' => 'push',
                'componentId' => 'queue',
                'driverName' => 'Sync',
                'jobClass' => 'app\\jobs\\OrderJob',
                'time' => 2.0,
            ],
            [
                'eventType' => 'push',
                'componentId' => 'queue',
                'driverName' => 'Sync',
                'jobClass' => 'app\\jobs\\EmailJob',
                'time' => 3.0,
            ],
        ];

        $searchModel = new QueueSearch();

        $dataProvider = $searchModel->search(
            ['Queue' => ['jobClass' => 'Hello']],
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
            [
                'eventType' => 'push',
                'componentId' => 'queue',
                'driverName' => 'Sync',
                'jobClass' => 'A',
                'jobId' => '101',
                'time' => 1.0,
            ],
            [
                'eventType' => 'push',
                'componentId' => 'queue',
                'driverName' => 'Sync',
                'jobClass' => 'A',
                'jobId' => '202',
                'time' => 2.0,
            ],
            [
                'eventType' => 'push',
                'componentId' => 'queue',
                'driverName' => 'Sync',
                'jobClass' => 'A',
                'jobId' => '303',
                'time' => 3.0,
            ],
        ];

        $searchModel = new QueueSearch();

        $dataProvider = $searchModel->search(
            ['Queue' => ['jobId' => '20']],
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
            [
                'eventType' => 'push',
                'componentId' => 'queue',
                'driverName' => 'Sync',
                'jobClass' => 'A',
                'time' => 1.0,
            ],
            [
                'eventType' => 'exec',
                'componentId' => 'queue',
                'driverName' => 'Sync',
                'jobClass' => 'A',
                'time' => 2.0,
            ],
            [
                'eventType' => 'push',
                'componentId' => 'queueRedis',
                'driverName' => 'Redis',
                'jobClass' => 'A',
                'time' => 3.0,
            ],
            [
                'eventType' => 'error',
                'componentId' => 'queue',
                'driverName' => 'Sync',
                'jobClass' => 'A',
                'time' => 4.0,
            ],
        ];

        $searchModel = new QueueSearch();

        $dataProvider = $searchModel->search(
            [
                'Queue' => [
                    'eventType' => 'push',
                    'componentId' => 'queue',
                ],
            ],
            $records,
        );

        self::assertCount(1, $dataProvider->getModels(), 'Only one record satisfies push + queue.');
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
    }

    public function testSearchFiltersByEventTypeExactMatch(): void
    {
        $records = [
            [
                'eventType' => 'push',
                'componentId' => 'queue',
                'driverName' => 'Sync',
                'jobClass' => 'A',
                'time' => 1.0,
            ],
            [
                'eventType' => 'exec',
                'componentId' => 'queue',
                'driverName' => 'Sync',
                'jobClass' => 'A',
                'time' => 2.0,
            ],
            [
                'eventType' => 'error',
                'componentId' => 'queue',
                'driverName' => 'Sync',
                'jobClass' => 'B',
                'time' => 3.0,
            ],
        ];

        $searchModel = new QueueSearch();

        $dataProvider = $searchModel->search(
            ['Queue' => ['eventType' => 'error']],
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
            $records[] = [
                'eventType' => 'push',
                'componentId' => 'queue',
                'driverName' => 'Sync',
                'jobClass' => 'app\\jobs\\HelloJob',
                'time' => (float) $i,
            ];
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
            [
                'eventType' => 'push',
                'componentId' => 'queue',
                'driverName' => 'Sync',
                'jobClass' => 'A',
                'time' => 1.0,
            ],
            [
                'eventType' => 'exec',
                'componentId' => 'queue',
                'driverName' => 'Sync',
                'jobClass' => 'B',
                'time' => 2.0,
            ],
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
