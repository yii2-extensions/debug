<?php

declare(strict_types=1);

namespace yii\debug\panels\profile;

use UIAwesome\Html\Helper\Encode;
use UIAwesome\Html\Phrasing\Span;
use yii\debug\helpers\{Fqcn, Gauge};

use function date;
use function sprintf;
use function str_repeat;

/**
 * Renders the typed cells of the profile grid for the Profiling debug panel.
 *
 * Stateless static helpers: every method takes a typed {@see ProfileRow} and returns the rendered cell, keeping the
 * GridView column closures in `panels/profile/detail.php` short and free of `mixed` narrowing.
 */
final class ProfileCellRenderer
{
    /**
     * Renders the category as the shared {@see Fqcn::renderLabel()} two-tone label: muted namespace prefix plus a
     * bold `Class::method` pair, with the full category preserved in the `title` attribute.
     */
    public static function renderCategoryCell(ProfileRow $row): string
    {
        return Fqcn::renderLabel($row->category);
    }

    /**
     * Renders the block duration formatted as `N.N ms`, with a micro-gauge rail scaled against the capture maximum
     * when one exists.
     *
     * @param ProfileRow $row Typed profile row.
     * @param float $maxDuration Capture maximum in milliseconds ({@see ProfileRowNormalizer::maxDuration()}).
     */
    public static function renderDurationCell(ProfileRow $row, float $maxDuration): string
    {
        return Gauge::render(
            sprintf('%.1f ms', $row->duration),
            $row->duration,
            $maxDuration,
        );
    }

    /**
     * Renders the info cell with one indentation arrow per nesting level, followed by the escaped info text.
     */
    public static function renderInfoCell(ProfileRow $row): string
    {
        $arrow = Span::tag()
            ->class('yii-debug-indent')
            ->content('→')
            ->render();

        return str_repeat($arrow, $row->level) . Encode::content($row->info);
    }

    /**
     * Renders the capture time as `H:i:s.mmm` with a full `Y-m-d H:i:s.mmm` hover tooltip, derived from the row's
     * millisecond timestamp.
     */
    public static function renderTimeCell(ProfileRow $row): string
    {
        $seconds = $row->timestamp / 1000;

        $millis = (int) (($seconds - (int) $seconds) * 1000);

        $suffix = sprintf('%03d', $millis);

        return Span::tag()
            ->title(date('Y-m-d H:i:s.', (int) $seconds) . $suffix)
            ->content(date('H:i:s.', (int) $seconds) . $suffix)
            ->render();
    }
}
