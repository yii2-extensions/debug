<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use DateTimeImmutable;
use LogicException;
use Override;

/**
 * Date value exposing private-capture hazards independently of its native timestamp.
 */
final class IdentityDateTime extends DateTimeImmutable
{
    public string $password = 'SYNTHETIC_DATE_PASSWORD';

    /**
     * @return array<string, mixed> Diagnostic data that capture must never request.
     */
    public function __debugInfo(): array
    {
        throw new LogicException(
            'Capture must not invoke arbitrary debug hooks.',
        );
    }

    #[Override]
    public function format(string $format): string
    {
        throw new LogicException(
            'Capture must use the native timestamp instead of overridden formatting.',
        );
    }
}
