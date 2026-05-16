<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\web\UrlRuleInterface;

/**
 * Stub implementation of {@see UrlRuleInterface} that always fails to create or parse URLs, used for testing purposes.
 */
class CustomRuleStub implements UrlRuleInterface
{
    /**
     * @param array<int|string, mixed> $params
     */
    public function createUrl($manager, $route, $params): bool
    {
        return false;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}|false
     */
    public function parseRequest($manager, $request): array|false
    {
        return false;
    }
}
