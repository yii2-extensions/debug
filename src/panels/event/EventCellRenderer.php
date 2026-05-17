<?php

declare(strict_types=1);

namespace yii\debug\panels\event;

use function date;
use function sprintf;

/**
 * Renders the typed cells of the events grid for the Event debug panel.
 *
 * Stateless static helpers: every method takes a typed {@see EventRow} and returns the rendered cell string, keeping
 * the GridView column closures in `panels/event/detail.php` free of `mixed` narrowing.
 */
final class EventCellRenderer
{
    /**
     * Returns the sender FQCN as plain text (`''` for static events).
     *
     * Acts as a typed pass-through that documents the column intent and keeps the view symmetric with the other panel
     * renderers.
     */
    public static function renderSenderCell(EventRow $row): string
    {
        return $row->senderClass;
    }

    /**
     * Renders the capture time as `H:i:s.mmm`, derived from the row's second-precision timestamp.
     */
    public static function renderTimeCell(EventRow $row): string
    {
        $seconds = (int) $row->time;
        $millis = (int) (($row->time - $seconds) * 1000);

        return date('H:i:s.', $seconds) . sprintf('%03d', $millis);
    }
}
