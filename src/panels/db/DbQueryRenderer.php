<?php

declare(strict_types=1);

namespace yii\debug\panels\db;

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\List\{Li, Ul};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use yii\debug\panels\DbPanel;

use function array_map;
use function date;
use function implode;
use function sprintf;

/**
 * Renders the typed cells of the queries grid for the DB debug panel.
 *
 * Stateless static helpers — every method takes a typed {@see QueryRow} (and any extra context the cell needs) and
 * returns the rendered HTML. Keeps the GridView column closures in `panels/db/queries.php` short and free of `mixed`
 * narrowing.
 *
 * Usage example:
 * ```php
 * 'value' => static fn(mixed $data): string => DbQueryRenderer::renderTypeCell(QueryRowNormalizer::from($data)),
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class DbQueryRenderer
{
    /**
     * Renders the duration formatted to one decimal millisecond.
     */
    public static function renderDurationCell(QueryRow $row): string
    {
        return sprintf('%.1f ms', $row->duration);
    }

    /**
     * Renders the SQL statement column with the optional trace list and EXPLAIN toggle.
     *
     * The caller resolves the EXPLAIN URL (typically via `Url::to(['db-explain', ...])`) so the renderer stays free of
     * routing concerns and easy to test in isolation.
     *
     * @param callable(int): string $explainUrlBuilder Builds the EXPLAIN URL for the given query sequence.
     */
    public static function renderQueryCell(
        QueryRow $row,
        DbPanel $panel,
        bool $hasExplain,
        callable $explainUrlBuilder,
    ): string {
        $children = [Div::tag()->class('yii-debug-db-sql')->content($row->query)];

        if ($row->trace !== []) {
            $items = array_map(
                static fn(array $frame): Li => Li::tag()->html($panel->getTraceLine($frame)),
                $row->trace,
            );

            $children[] = Ul::tag()
                ->class('yii-debug-trace')
                ->html(...$items);
        }

        if ($hasExplain && DbPanel::canBeExplained($row->type)) {
            $children[] = Div::tag()
                ->class('yii-debug-db-explain')
                ->html(
                    A::tag()
                        ->addAriaAttribute('expanded', 'false')
                        ->addAriaAttribute('label', 'Toggle EXPLAIN output')
                        ->class('yii-debug-db-explain-toggle')
                        ->href($explainUrlBuilder($row->seq))
                        ->html(
                            Span::tag()
                                ->addAriaAttribute('hidden', 'true')
                                ->class('yii-debug-db-explain-chevron')
                                ->content('›'),
                            Span::tag()
                                ->class('yii-debug-db-explain-label')
                                ->content('Explain'),
                        )
                        ->role('button'),
                    Div::tag()
                        ->class('yii-debug-db-explain-text'),
                );
        }

        return implode('', array_map(static fn(Div|Ul $el): string => $el->render(), $children));
    }

    /**
     * Renders the rows-affected cell. Returns an em dash when the driver did not report the count.
     */
    public static function renderRowsCell(QueryRow $row): string
    {
        if ($row->rows === null) {
            return '–';
        }

        return $row->rows . ' ' . ($row->rows === 1 ? 'row' : 'rows');
    }

    /**
     * Renders the `H:i:s.mmm` timestamp derived from the millisecond field.
     */
    public static function renderTimeCell(QueryRow $row): string
    {
        $timeInSeconds = $row->timestamp / 1000;

        $millisecondsDiff = (int) (($timeInSeconds - (int) $timeInSeconds) * 1000);

        return date('H:i:s.', (int) $timeInSeconds) . sprintf('%03d', $millisecondsDiff);
    }

    /**
     * Renders the colored type pill (`SELECT`, `INSERT`, ...).
     */
    public static function renderTypeCell(QueryRow $row): string
    {
        $variant = DbPanel::typeBadgeVariant($row->type);

        return Span::tag()
            ->class("yii-debug-db-type yii-debug-db-type-{$variant}")
            ->content($row->type)
            ->render();
    }
}
