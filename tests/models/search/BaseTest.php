<?php

declare(strict_types=1);

namespace yii\debug\tests\models\search;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\models\search\Base;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see Base} covering the shared filter contract every panel search model inherits.
 *
 * @since 0.2
 */
#[Group('search')]
final class BaseTest extends TestCase
{
    public function testRowsMissingTheFilteredAttributeAreRejected(): void
    {
        $this->mockWebApplication();

        $search = new class extends Base {
            public string $missing = 'anything';

            /**
             * @param list<object> $rows Rows to filter.
             *
             * @return list<object> Rows matching the registered condition.
             */
            public function apply(array $rows): array
            {
                $this->addCondition('missing');

                return $this->filter($rows);
            }
        };

        $row = new class {
            public string $present = 'anything';
        };

        self::assertSame([], $search->apply([$row]), 'A row without the filtered attribute cannot match.');
    }
}
