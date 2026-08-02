<?php

declare(strict_types=1);

namespace yii\debug\panels\user;

use yii\debug\storage\{ArrayPayloadSnapshot, PanelSnapshot};

/**
 * Canonical User panel identity and RBAC snapshot.
 */
final readonly class UserSnapshot implements PanelSnapshot
{
    use ArrayPayloadSnapshot;

    private const string KEY = 'data';

    /**
     * @return array<array-key, mixed> Captured identity attributes, roles, and permissions.
     */
    public function data(): array
    {
        return $this->values();
    }
}
