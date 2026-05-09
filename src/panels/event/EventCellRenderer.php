<?php

declare(strict_types=1);

namespace yii\debug\panels\event;

use function date;
use function sprintf;

/**
 * Renders the typed cells of the events grid for the Event debug panel.
 *
 * Stateless static helpers; every method takes a typed {@see EventRow} and returns the rendered cell string. Keeps
 * the GridView column closures in `panels/event/detail.php` free of `mixed` narrowing.
 *
 * Usage example:
 * ```php
 * 'value' => static fn(mixed $data): string => EventCellRenderer::renderTimeCell(EventRowNormalizer::from($data)),
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class EventCellRenderer
{
    /**
     * Renders the sender FQCN as plain text. The DTO already guarantees a string (empty for static events) so this
     * is a typed pass-through that documents the column intent and keeps the view symmetric with the rest of the
     * panel renderers.
     */
    public static function renderSenderCell(EventRow $row): string
    {
        return $row->senderClass;
    }

    /**
     * Renders the `H:i:s.mmm` timestamp for the event capture time.
     */
    public static function renderTimeCell(EventRow $row): string
    {
        $seconds = (int) $row->time;
        $millis = (int) (($row->time - $seconds) * 1000);

        return date('H:i:s.', $seconds) . sprintf('%03d', $millis);
    }
}
