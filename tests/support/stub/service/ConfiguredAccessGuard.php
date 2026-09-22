<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\service;

use Override;
use yii\base\Action;
use yii\debug\Module;
use yii\debug\service\AccessGuard;

/**
 * Stub guard exposing the module handed to its constructor and forcing the access decision.
 */
final class ConfiguredAccessGuard extends AccessGuard
{
    /**
     * @var list<string> Requesting IP addresses received by {@see allows()}, in call order.
     */
    public array $calls = [];
    /**
     * @var string Value fed by a configuration array to prove property configuration is applied.
     */
    public string $label = '';
    /**
     * @var bool Decision returned instead of evaluating the module configuration.
     */
    public bool $outcome = false;

    #[Override]
    public function allows(string $ip, Action|null $action = null): bool
    {
        $this->calls[] = $ip;

        return $this->outcome;
    }

    /**
     * Returns the module received as the constructor argument.
     */
    public function boundModule(): Module
    {
        return $this->module;
    }
}
