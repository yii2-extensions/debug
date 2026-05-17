<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\router\module;

use yii\base\Module;

/**
 * Stub module for testing the router with a controller that throws an exception in {@see init()} and simulating a
 * scenario where `getModule()` returns `null` for a child module.
 */
final class NullGetModuleStub extends Module
{
    public $controllerNamespace = 'yii\\not_a_real_namespace\\controllers';

    public function getModule($id, $load = true): Module|null
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getModules($loadedOnly = false): array
    {
        return ['nonexistent' => 'stub'];
    }
}
