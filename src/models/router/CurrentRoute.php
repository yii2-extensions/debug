<?php

declare(strict_types=1);

namespace yii\debug\models\router;

use yii\base\Model;
use yii\log\Logger;

use function is_string;

/**
 * CurrentRoute model
 */
class CurrentRoute extends Model
{
    /**
     * @var array logged messages.
     */
    public array $messages = [];
    /**
     * @var string logged route.
     */
    public string $route = '';
    /**
     * @var string logged action.
     */
    public string $action = '';
    /**
     * @var string|null info message.
     */
    public string|null $message = null;
    /**
     * @var array logged rules.
     * ```php
     * [
     *  [
     *      'rule' => (string),
     *      'match' => (bool),
     *      'parent'=> parent class (string)
     *  ]
     * ]
     * ```
     */
    public array $logs = [];
    /**
     * @var int count, before match.
     */
    public int $count = 0;
    /**
     * @var bool
     */
    public bool $hasMatch = false;

    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        $last = null;
        foreach ($this->messages as $message) {
            if ($message[1] === Logger::LEVEL_TRACE && is_string($message[0])) {
                $this->message = $message[0];
            } elseif (isset($message[0]['rule'], $message[0]['match'])) {
                if (!empty($last['parent']) && $last['parent'] === $message[0]['rule']) {
                    continue;
                }
                $this->logs[] = $message[0];
                ++$this->count;
                if ($message[0]['match']) {
                    $this->hasMatch = true;
                }
                $last = $message[0];
            }
        }
    }
}
