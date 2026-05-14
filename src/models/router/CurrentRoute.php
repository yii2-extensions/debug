<?php

declare(strict_types=1);

namespace yii\debug\models\router;

use yii\base\Model;
use yii\log\Logger;

use function is_array;
use function is_bool;
use function is_string;

/**
 * Reconstructs the URL-rule match log of the active request from the captured logger messages.
 *
 * Replays the raw log entries supplied via {@see $messages} during {@see init()} to expose the matched action, the
 * resolved route, the trace of rules tried, and a derived flag indicating whether the URL manager actually matched.
 */
class CurrentRoute extends Model
{
    /**
     * Resolved action route logged for the current request.
     */
    public string $action = '';
    /**
     * Number of URL rules inspected before a match (or until the trace ended).
     */
    public int $count = 0;
    /**
     * Whether any inspected rule reported a successful match.
     */
    public bool $hasMatch = false;
    /**
     * @var list<array{rule: string, match: bool, parent?: string}> Normalized trace of URL rules inspected during
     * routing, in inspection order.
     */
    public array $logs = [];
    /**
     * Trace-level info message captured for the routing pass, when present.
     */
    public string|null $message = null;
    /**
     * @var array<int, array{0: mixed, 1: int, 2?: string, 3?: float, 4?: array<int, array<string, mixed>>}> Raw logger
     * messages captured for the routing pass, consumed by {@see init()}.
     */
    public array $messages = [];
    /**
     * Resolved request route logged for the current request.
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
     * Narrows a raw logger payload into the `{rule, match, parent?}` shape consumed by the view layer.
     *
     * @param mixed $message Raw logger payload.
     *
     * @return array{rule: string, match: bool, parent?: string}|null Normalized entry, or `null` when the payload does
     * not have the expected shape.
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
