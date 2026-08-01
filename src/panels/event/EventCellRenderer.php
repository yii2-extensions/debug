<?php

declare(strict_types=1);

namespace yii\debug\panels\event;

use UIAwesome\Html\Phrasing\{Span, Strong};
use yii\debug\helpers\Fqcn;

use function date;
use function sprintf;

/**
 * Renders the typed cells of the events grid for the Event debug panel.
 *
 * Stateless static helpers: every method takes a typed {@see EventRow} and returns the rendered cell string, keeping
 * the GridView column closures in `panels/event/detail.php` free of `mixed` narrowing. Class cells split the FQCN into
 * a muted namespace prefix and a bold short name, mirroring the asset and queue renderers.
 */
final class EventCellRenderer
{
    /**
     * Renders the event class FQCN as a muted namespace prefix plus a bold short name (`—` when empty).
     */
    public static function renderClassCell(EventRow $row): string
    {
        return self::renderFqcn($row->class);
    }

    /**
     * Renders the sender FQCN as a muted namespace prefix plus a bold short name (`—` for static events).
     */
    public static function renderSenderCell(EventRow $row): string
    {
        return self::renderFqcn($row->senderClass);
    }

    /**
     * Renders a muted `static` badge for class-level events, or `—` for object-level ones.
     */
    public static function renderStaticCell(EventRow $row): string
    {
        if ($row->isStatic !== '1') {
            return '—';
        }

        return Span::tag()
            ->class('yii-debug-badge yii-debug-badge-muted')
            ->content('static')
            ->render();
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

    /**
     * Splits `$fqcn` into a muted namespace prefix and a bold short name, keeping the full FQCN in the `title`
     * attribute for hover inspection (`—` when empty).
     */
    private static function renderFqcn(string $fqcn): string
    {
        if ($fqcn === '') {
            return '—';
        }

        $namespace = Fqcn::namespacePart($fqcn);

        return Span::tag()
            ->title($fqcn)
            ->html(
                $namespace !== ''
                    ? Span::tag()
                        ->class('yii-debug-muted')
                        ->content("{$namespace}\\")
                    : '',
                Strong::tag()->content(Fqcn::shortName($fqcn)),
            )
            ->render();
    }
}
