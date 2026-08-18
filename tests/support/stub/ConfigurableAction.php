<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\base\Action;

/**
 * Stub standalone action exposing a configurable property for action-map instantiation tests.
 */
final class ConfigurableAction extends Action
{
    public string $label = 'default';

    public function run(): string
    {
        return $this->label;
    }
}
