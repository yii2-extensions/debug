<?php

declare(strict_types=1);

namespace yii\debug\models\router;

use PHPForge\Debug\Panel\Router\{CurrentRouteLogRow, RouterSnapshot};
use yii\base\Model;

use function count;

/**
 * Exposes the URL-rule match log of the active request, already resolved at capture time.
 *
 * The trace replay lives in {@see RouterSnapshot::capture()}, so this model is a plain view surface over the typed
 * rows: the matched action, the resolved route, the rules tried, and whether the URL manager matched.
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
     * @var list<CurrentRouteLogRow> Rules inspected during routing, in inspection order.
     */
    public array $logs = [];
    /**
     * Trace-level info message captured for the routing pass, when present.
     */
    public string|null $message = null;
    /**
     * Resolved request route logged for the current request.
     */
    public string $route = '';

    /**
     * Builds the view model from a hydrated router snapshot, or an empty one when the panel captured nothing.
     */
    public static function fromSnapshot(RouterSnapshot|null $snapshot): self
    {
        if ($snapshot === null) {
            return new self();
        }

        $route = new self();

        $route->action = $snapshot->action ?? '';
        $route->route = $snapshot->route;
        $route->message = $snapshot->message;
        $route->logs = $snapshot->entries();
        $route->count = count($route->logs);
        $route->hasMatch = $snapshot->hasMatch();

        return $route;
    }
}
