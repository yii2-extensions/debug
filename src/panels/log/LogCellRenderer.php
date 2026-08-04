<?php

declare(strict_types=1);

namespace yii\debug\panels\log;

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Helper\Encode;
use UIAwesome\Html\List\{Li, Ul};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use yii\debug\helpers\{CellMore, Fqcn, Vocabulary};
use yii\debug\panels\db\SqlHighlighter;
use yii\debug\panels\LogPanel;
use yii\log\Logger;

use function array_map;
use function date;
use function implode;
use function sprintf;
use function str_starts_with;

/**
 * Renders the typed cells of the logs grid for the Log debug panel.
 *
 * Stateless static helpers: every method takes a typed {@see LogRow} and returns the rendered cell, keeping the
 * GridView column closures in `panels/log/detail.php` short and free of `mixed` narrowing.
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
     * Category prefix of the DB command log entries whose message is a raw SQL statement
     * ({@see \yii\debug\panels\DbPanel::$dbEventNames} defaults).
     */
    private const string SQL_CATEGORY_PREFIX = 'yii\db\Command::';

    /**
     * Builds the GridView `rowOptions` array for the row: anchor id (`log-{N}`) and severity-driven CSS class.
     *
     * @return array<string, mixed> Attribute map with `id` and (optionally) `class` keys.
     */
    public static function buildRowOptions(LogRow $row): array
    {
        $variant = self::LEVEL_VARIANTS[$row->level] ?? null;

        $options = [
            'id' => "log-{$row->id}",
        ];

        if ($variant !== null) {
            $options['class'] = "yii-debug-row-{$variant}";
        }

        return $options;
    }

    /**
     * Renders the category as the shared {@see Fqcn::renderLabel()} two-tone label: muted namespace prefix plus a
     * bold `Class::method` pair, with the full category preserved in the `title` attribute.
     */
    public static function renderCategoryCell(LogRow $row): string
    {
        return Fqcn::renderLabel($row->category);
    }

    /**
     * Renders the level name as a vocabulary-tinted chip (`error`, `warning`, `info`, `trace`, `profile`).
     */
    public static function renderLevelCell(LogRow $row): string
    {
        return Span::tag()
            ->class('yii-debug-level-chip yii-debug-level-' . Vocabulary::logLevel($row->level))
            ->content(Logger::getLevelName($row->level))
            ->render();
    }

    /**
     * Renders the message cell, followed by the optional trace list.
     *
     * The row holds the message as a display string (already exported when the source was non-string), so the renderer
     * escapes it once — except for DB command entries, whose raw SQL message renders through
     * {@see SqlHighlighter::highlight()} with the same token spans as the db panel queries grid. Long messages
     * collapse behind the {@see CellMore} clamp.
     *
     * @param LogRow $row Typed log record.
     * @param LogPanel $panel Panel used to render each trace line.
     */
    public static function renderMessageCell(LogRow $row, LogPanel $panel): string
    {
        $body = str_starts_with($row->category, self::SQL_CATEGORY_PREFIX)
            ? Div::tag()->class('yii-debug-db-sql')->html(SqlHighlighter::highlight($row->message))->render()
            : Encode::content($row->message);

        $body = CellMore::clamp($body, $row->message);

        if ($row->trace === []) {
            return $body;
        }

        $items = array_map(
            static fn(array $frame): Li => Li::tag()->html($panel->getTraceLine($frame)),
            $row->trace,
        );

        $trace = Ul::tag()->class('yii-debug-trace')->html(...$items)->render();

        return "{$body}{$trace}";
    }

    /**
     * Renders the capture time as `H:i:s.mmm`, derived from the row's millisecond timestamp.
     */
    public static function renderTimeCell(LogRow $row): string
    {
        $seconds = $row->time / 1000;

        $millis = (int) (($seconds - (int) $seconds) * 1000);

        return date('H:i:s.', (int) $seconds) . sprintf('%03d', $millis);
    }

    /**
     * Renders the time-since-previous cell with the prev/next anchor navigation buttons.
     *
     * Disabled buttons replace the anchors when the row is the first or the last of the request.
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
     * Renders one nav arrow as either a disabled `<span>` when `$targetId` is `null`, or an `<a>` linking to
     * `#log-{targetId}` otherwise.
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
