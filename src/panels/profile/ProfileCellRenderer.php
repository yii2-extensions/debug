<?php

declare(strict_types=1);

namespace yii\debug\panels\profile;

use UIAwesome\Html\Phrasing\Span;
use yii\helpers\Html;

use function date;
use function sprintf;
use function str_repeat;

/**
 * Renders the typed cells of the profile grid for the Profiling debug panel.
 *
 * Stateless static helpers; every method takes a typed {@see ProfileRow} and returns the rendered cell. Keeps the
 * GridView column closures in `panels/profile/detail.php` short and free of `mixed` narrowing.
 *
 * Usage example:
 * ```php
 * 'value' => static fn(mixed $data): string => ProfileCellRenderer::renderTimeCell(ProfileRowNormalizer::from($data)),
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class ProfileCellRenderer
{
    /**
     * Renders the duration formatted to one decimal millisecond.
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
        $arrow = Span::tag()->class('yii-debug-indent')->content('→')->render();

        return str_repeat($arrow, $row->level) . Html::encode($row->info);
    }

    /**
     * Renders the `H:i:s.mmm` timestamp derived from the millisecond field.
     */
    public static function renderTimeCell(ProfileRow $row): string
    {
        $seconds = $row->timestamp / 1000;

        $millis = (int) (($seconds - (int) $seconds) * 1000);

        return date('H:i:s.', (int) $seconds) . sprintf('%03d', $millis);
    }
}
