<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use Override;
use yii\base\{Action, InvalidConfigException};

/**
 * Stub standalone action that requires a configured property, for action-map config-preservation tests.
 */
final class RequiredOptionAction extends Action
{
    public string $requiredOption = '';

    #[Override]
    public function init(): void
    {
        parent::init();

        if ($this->requiredOption === '') {
            throw new InvalidConfigException('requiredOption must be configured.');
        }
    }

    public function run(): void {}
}
