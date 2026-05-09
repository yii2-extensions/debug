<?php

declare(strict_types=1);

namespace yii\debug\panels\log;

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\List\{Li, Ul};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use yii\debug\panels\LogPanel;
use yii\helpers\Html;
use yii\log\Logger;

use function array_map;
use function date;
use function implode;
use function sprintf;

/**
 * Renders the typed cells of the logs grid for the Log debug panel.
 *
 * Stateless static helpers; every method takes a typed {@see LogRow} and returns the rendered cell. Keeps the GridView
 * column closures in `panels/log/detail.php` short and free of `mixed` narrowing.
 *
 * Usage example:
 * ```php
 * 'value' => static fn(mixed $data): string => LogCellRenderer::renderTimeCell(LogRowNormalizer::from($data)),
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class LogCellRenderer
{
    /**
     * @var array<int, string> Maps the four severity levels surfaced in the grid to the row variant CSS modifier.
     */
    private const array LEVEL_VARIANTS = [
        Logger::LEVEL_ERROR => 'danger',
        Logger::LEVEL_WARNING => 'warning',
        Logger::LEVEL_INFO => 'info',
    ];

    /**
     * Builds the GridView `rowOptions` array for the row: anchor id (`log-{N}`) and severity-driven CSS class.
     *
     * @return array<string, mixed>
     */
    public static function buildRowOptions(LogRow $row): array
    {
        $variant = self::LEVEL_VARIANTS[$row->level] ?? null;

        $options = [
            'id' => "log-{$row->id}",
        ];

        if ($variant !== null) {
            $options['class'] = "yii-debug-row--{$variant}";
        }

        return $options;
    }

    /**
     * Renders the human-readable level name (`error`, `warning`, etc.).
     */
    public static function renderLevelCell(LogRow $row): string
    {
        return Logger::getLevelName($row->level);
    }

    /**
     * Renders the message cell with the optional trace list. The DTO holds the message as a display string (already
     * exported when the source was non-string) so the renderer just escapes it once.
     */
    public static function renderMessageCell(LogRow $row, LogPanel $panel): string
    {
        $body = Html::encode($row->message);

        if ($row->trace === []) {
            return $body;
        }

        $items = array_map(
            static fn(array $frame): Li => Li::tag()->html($panel->getTraceLine($frame)),
            $row->trace,
        );

        return $body . Ul::tag()->class('yii-debug-trace')->html(...$items)->render();
    }

    /**
     * Renders the `H:i:s.mmm` timestamp derived from the millisecond field.
     */
    public static function renderTimeCell(LogRow $row): string
    {
        $seconds = $row->time / 1000;

        $millis = (int) (($seconds - (int) $seconds) * 1000);

        return date('H:i:s.', (int) $seconds) . sprintf('%03d', $millis);
    }

    /**
     * Renders the time-since-previous cell with the prev/next anchor navigation buttons. Disabled buttons replace the
     * anchors when the row is the first or the last of the request.
     */
    public static function renderTimeSincePreviousCell(LogRow $row): string
    {
        $diffMsTotal = $row->time - $row->timeOfPrevious;

        $diffSecondsTotal = $diffMsTotal / 1000;
        $diffMinutesTotal = $diffSecondsTotal / 60;
        $diffHoursTotal = $diffMinutesTotal / 60;

        $diffMs = (int) $diffMsTotal % 1000;
        $diffSeconds = (int) $diffSecondsTotal % 60;
        $diffMinutes = (int) $diffMinutesTotal % 60;
        $diffHours = (int) $diffHoursTotal;

        $parts = [];

        if ($diffHours > 0) {
            $parts[] = $diffHours . 'h';
        }

        if ($diffMinutes > 0) {
            $parts[] = $diffMinutes . 'm';
        }

        if ($diffSeconds > 0) {
            $parts[] = $diffSeconds . 's';
        }

        $parts[] = $diffMs . 'ms';

        return Div::tag()
            ->class('yii-debug-since-previous')
            ->html(
                self::renderNavButton('<', $row->idOfPrevious),
                Span::tag()->html(implode("\u{00A0}", $parts)),
                self::renderNavButton('>', $row->idOfNext),
            )
            ->render();
    }

    /**
     * Renders one nav arrow as either a disabled `<span>` (when `$targetId` is `null`) or an `<a>` linking to
     * `#log-{targetId}`.
     */
    private static function renderNavButton(string $glyph, int|null $targetId): A|Span
    {
        $class = 'yii-debug-since-previous-btn';

        if ($targetId === null) {
            return Span::tag()
                ->class("{$class} is-disabled")
                ->content($glyph);
        }

        return A::tag()
            ->class($class)
            ->content($glyph)
            ->href("#log-{$targetId}");
    }
}
