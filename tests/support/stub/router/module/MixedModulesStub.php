<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\router\module;

use Override;
use yii\base\Module;
use yii\debug\tests\support\stub\router\controllers\WebController;

/**
 * Module fixture exposing invalid entries before a valid child module.
 */
final class MixedModulesStub extends Module
{
    private Module|null $validModule = null;

    #[Override]
    public function getModule($id, $load = true): Module|null
    {
        return $id === 'valid' ? $this->validModule : null;
    }

    /**
     * @return array<int|string, mixed>
     */
    #[Override]
    public function getModules($loadedOnly = false): array
    {
        return [0 => 'invalid', 'missing' => 'invalid', 'valid' => Module::class];
    }

    #[Override]
    public function init(): void
    {
        $this->controllerNamespace = 'yii\\not_a_real_namespace\\controllers';

        $this->validModule = new Module(
            'valid',
            $this,
            [
                'controllerNamespace' => 'yii\\not_a_real_namespace\\controllers',
                'controllerMap' => ['mapped' => WebController::class],
            ],
        );
    }
}
