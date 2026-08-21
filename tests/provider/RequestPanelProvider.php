<?php

declare(strict_types=1);

namespace yii\debug\tests\provider;

use yii\debug\tests\request\RequestPanelTest;

/**
 * Data provider for {@see RequestPanelTest} test cases.
 *
 * Provides invalid stored status code representations for hydration tests.
 */
final class RequestPanelProvider
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidStoredStatusCodes(): iterable
    {
        yield 'non-numeric string' => ['not-a-number'];
        yield 'numeric string' => ['404'];
    }
}
