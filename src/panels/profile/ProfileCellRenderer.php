<?php

declare(strict_types=1);

namespace yii\debug\panels\profile;

use UIAwesome\Html\Helper\Encode;
use UIAwesome\Html\Phrasing\Span;

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
     * Renders the block duration formatted as `N.N ms`.
     */
    public static function renderDurationCell(ProfileRow $row): string
    {
        return sprintf('%.1f ms', $row->duration);
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
     * Renders the capture time as `H:i:s.mmm`, derived from the row's millisecond timestamp.
     */
    public static function renderTimeCell(ProfileRow $row): string
    {
        $seconds = $row->timestamp / 1000;

        $millis = (int) (($seconds - (int) $seconds) * 1000);

        return date('H:i:s.', (int) $seconds) . sprintf('%03d', $millis);
    }
}
