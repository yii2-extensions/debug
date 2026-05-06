<?php

declare(strict_types=1);

namespace yii\debug\models\router;

use yii\base\Model;
use yii\log\Logger;

use function is_array;
use function is_bool;
use function is_string;

/**
 * Represents the currently matched route and its related information, such as the action, logged rules, and messages.
 */
class CurrentRoute extends Model
{
    /**
     * Logged action.
     */
    public string $action = '';
    /**
     * Count, before match.
     */
    public int $count = 0;
    /**
     * Whether a match has been found.
     */
    public bool $hasMatch = false;
    /**
     * @var list<array{rule: string, match: bool, parent?: string}> logged rules.
     */
    public array $logs = [];
    /**
     * Info message.
     */
    public string|null $message = null;
    /**
     * @var array<int, array{0: mixed, 1: int, 2?: string, 3?: float, 4?: array<int, array<string, mixed>>}> logged
     * messages.
     */
    public array $messages = [];
    /**
     * Logged route.
     */
    public string $route = '';

    public function init(): void
    {
        parent::init();

        $last = null;

        foreach ($this->messages as $message) {
            if ($message[1] === Logger::LEVEL_TRACE && is_string($message[0])) {
                $this->message = $message[0];
                continue;
            }

            $log = $this->normalizeLogMessage($message[0]);

            if ($log !== null) {
                $previousParent = $last['parent'] ?? null;

                if ($previousParent !== null && $previousParent !== '' && $previousParent === $log['rule']) {
                    continue;
                }

                $this->logs[] = $log;
                ++$this->count;

                if ($log['match']) {
                    $this->hasMatch = true;
                }

                $last = $log;
            }
        }
    }

    /**
     * Normalizes log message to the format: ['rule' => string, 'match' => bool, 'parent' => string|null].
     *
     * @param mixed $message Log message to normalize.
     *
     * @return array{rule: string, match: bool, parent?: string}|null Normalized log message or null if the input
     * message is not in the expected format.
     */
    private function normalizeLogMessage($message): array|null
    {
        if (
            !is_array($message)
            || !isset($message['rule'], $message['match'])
            || !is_string($message['rule'])
            || !is_bool($message['match'])
        ) {
            return null;
        }

        $log = [
            'match' => $message['match'],
            'rule' => $message['rule'],
        ];

        if (isset($message['parent']) && is_string($message['parent'])) {
            $log['parent'] = $message['parent'];
        }

        return $log;
    }
}
