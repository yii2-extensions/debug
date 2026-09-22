<?php

declare(strict_types=1);

namespace yii\debug\service;

use Yii;
use yii\base\Action;
use yii\debug\exception\Message;
use yii\debug\{IpAllowlist, Module};

/**
 * Decides whether a request may reach the debugger.
 *
 * The IP and host allowlists are evaluated first, so a denial by address never runs the application callback.
 */
class AccessGuard
{
    /**
     * @param Module $module Debug module read for the access configuration at call time.
     */
    public function __construct(protected readonly Module $module) {}

    /**
     * Returns whether the request is allowed to reach the debugger.
     *
     * Checks {@see Module::$allowedIPs} and {@see Module::$allowedHosts}, then the optional
     * {@see Module::$checkAccessCallback}, which grants access only by returning `true`. A denial is reported through
     * {@see Yii::warning()} unless the matching `disable*RestrictionWarning` flag is set.
     *
     * @param string $ip Requesting IP address, or `''` when the request carries none.
     * @param Action|null $action Action being dispatched, or `null` outside an action context.
     *
     * @return bool `true` when the request may reach the debugger; `false` otherwise.
     */
    public function allows(string $ip, Action|null $action = null): bool
    {
        $allowed = (new IpAllowlist($this->module->allowedIPs, $this->module->allowedHosts))->matches($ip);

        if ($allowed === false) {
            if (!$this->module->disableIpRestrictionWarning) {
                Yii::warning(
                    "Access to debugger is denied due to IP address restriction. The requesting IP address is {$ip}",
                    __METHOD__,
                );
            }

            return false;
        }

        if ($this->module->checkAccessCallback !== null && ($this->module->checkAccessCallback)($action) !== true) {
            if (!$this->module->disableCallbackRestrictionWarning) {
                Yii::warning(
                    Message::ACCESS_DENIED_BY_CALLBACK->value,
                    __METHOD__,
                );
            }

            return false;
        }

        return true;
    }
}
