<?php

declare(strict_types=1);

namespace yii\debug\panels\router;

use PHPForge\Debug\Storage\{PanelRow, Payload};

use function is_array;
use function is_bool;
use function is_string;

/**
 * Typed row of the Current Route rules-tested log, resolved once from the URL-manager trace.
 */
final readonly class CurrentRouteLogRow implements PanelRow
{
    public function __construct(
        /**
         * Rule name or its class FQCN as captured by the route resolver.
         */
        public string $rule,
        /**
         * Parent rule ({@see \yii\rest\UrlRule} for nested REST rules), or `''` when there is no parent.
         */
        public string $parent,
        /**
         * `true` when this rule produced the final match; the renderer surfaces the row with a success modifier.
         */
        public bool $match,
    ) {}

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)->shape(['rule', 'parent', 'match']);

        return new self($payload->string('rule'), $payload->string('parent'), $payload->bool('match'));
    }

    /**
     * Narrows one raw URL-manager trace payload, returning `null` when it does not carry a rule/match pair.
     *
     * @param mixed $message Raw logger payload.
     */
    public static function fromLogMessage(mixed $message): self|null
    {
        if (
            !is_array($message)
            || !isset($message['rule'], $message['match'])
            || !is_string($message['rule'])
            || !is_bool($message['match'])
        ) {
            return null;
        }

        $parent = $message['parent'] ?? null;

        return new self($message['rule'], is_string($parent) ? $parent : '', $message['match']);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'rule' => $this->rule,
            'parent' => $this->parent,
            'match' => $this->match,
        ];
    }
}
