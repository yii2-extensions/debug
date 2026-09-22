<?php

declare(strict_types=1);

namespace yii\debug\tests\service;

use PHPUnit\Framework\Attributes\Group;
use Xepozz\InternalMocker\MockerState;
use yii\debug\service\CoreDefinitions;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see CoreDefinitions} merging built-in definitions with the ones the application declares.
 */
#[Group('service')]
final class CoreDefinitionsTest extends TestCase
{
    public function testMergeAppendsConfiguredEntriesAfterTheBuiltIns(): void
    {
        self::assertSame(
            ['log' => 'core-log', 'request' => 'core-request', 'app.example' => 'custom'],
            CoreDefinitions::merge(
                ['log' => 'core-log', 'request' => 'core-request'],
                ['app.example' => 'custom'],
            ),
            'Configured entries must follow the built-ins.',
        );
    }

    public function testMergeDropsABuiltInWhoseOptionalPackageIsMissing(): void
    {
        MockerState::addCondition(
            'yii\debug',
            'class_exists',
            ['yii\queue\Queue'],
            false,
        );

        self::assertSame(
            ['log' => 'core-log'],
            CoreDefinitions::merge(['log' => 'core-log', 'queue' => 'core-queue'], []),
            'An unavailable integration must not survive the merge.',
        );
    }

    public function testMergeKeepsAConfiguredEntryForAnUnavailableBuiltIn(): void
    {
        MockerState::addCondition(
            'yii\debug',
            'class_exists',
            ['yii\queue\Queue'],
            false,
        );

        self::assertSame(
            ['queue' => 'custom-queue'],
            CoreDefinitions::merge(['queue' => 'core-queue'], ['queue' => 'custom-queue']),
            'Explicit configuration must override availability detection.',
        );
    }

    public function testMergeMovesAnOverriddenBuiltInToTheConfiguredPosition(): void
    {
        self::assertSame(
            ['request' => 'core-request', 'log' => 'custom-log'],
            CoreDefinitions::merge(
                ['log' => 'core-log', 'request' => 'core-request'],
                ['log' => 'custom-log'],
            ),
            'Override must move the entry to its configured slot.',
        );
    }

    public function testMergeRenumbersConfiguredEntriesDeclaredAsAList(): void
    {
        self::assertSame(
            ['log' => 'core-log', 0 => 'first', 1 => 'second'],
            CoreDefinitions::merge(['log' => 'core-log'], ['first', 'second']),
            'A list-shaped configuration must keep its order under integer keys.',
        );
    }

    public function testMergeReturnsTheConfiguredEntriesWhenNoBuiltInIsDeclared(): void
    {
        self::assertSame(
            ['app.example' => 'custom'],
            CoreDefinitions::merge([], ['app.example' => 'custom']),
            'An empty built-in map must contribute nothing.',
        );
    }
}
