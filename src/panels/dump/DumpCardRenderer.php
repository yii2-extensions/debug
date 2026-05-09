<?php

declare(strict_types=1);

namespace yii\debug\panels\dump;

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\List\{Li, Ul};
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Root\Header;
use yii\debug\panels\DumpPanel;

use function array_map;
use function basename;
use function date;
use function floor;
use function html_entity_decode;
use function in_array;
use function is_int;
use function is_string;
use function ltrim;
use function preg_match;
use function preg_replace;
use function sprintf;
use function strip_tags;
use function strtolower;

/**
 * Renders the typed dump cells of the dumps grid for the Dump debug panel.
 *
 * Stateless static helpers; every method takes a typed {@see DumpRow} (and any extra context the cell needs) and
 * returns the rendered HTML. Keeps the GridView column closure in `panels/dump/detail.php` short and free of `mixed`
 * narrowing.
 *
 * Usage example:
 * ```php
 * 'value' => static fn(mixed $data, $key, int $index): string => DumpCardRenderer::renderMessageCell(
 *     DumpRowNormalizer::from($data),
 *     $panel,
 *     $index,
 * ),
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class DumpCardRenderer
{
    /**
     * Renders the dump card combining the head (`#index`, type badge, time, trace label) and the body (highlighted
     * payload + optional trace list).
     *
     * @param int $index Zero-based row index assigned by GridView.
     */
    public static function renderMessageCell(DumpRow $row, DumpPanel $panel, int $index): string
    {
        return Div::tag()
            ->class('yii-debug-dump')
            ->html(
                self::renderHead($row, $index),
                self::renderBody($row, $panel),
            )
            ->render();
    }

    /**
     * Extracts the first trace frame's `file` / `line` pair (both narrowed to `string` / `int|null`).
     *
     * @param list<array<string, mixed>> $trace
     *
     * @return array{0: string, 1: int|null}
     */
    private static function firstFrame(array $trace): array
    {
        $frame = $trace[0] ?? null;

        if ($frame === null) {
            return ['', null];
        }

        $file = is_string($frame['file'] ?? null) ? $frame['file'] : '';
        $line = is_int($frame['line'] ?? null) ? $frame['line'] : null;

        return [$file, $line];
    }

    /**
     * Formats a Unix timestamp in seconds to `H:i:s.mmm`, or returns an empty string when no timestamp is set.
     */
    private static function formatTime(float $time): string
    {
        if ($time <= 0) {
            return '';
        }

        $millis = (int) (($time - floor($time)) * 1000);

        return date('H:i:s', (int) $time) . '.' . sprintf('%03d', $millis);
    }

    /**
     * Renders the dump card body: highlighted payload followed by the optional trace list.
     */
    private static function renderBody(DumpRow $row, DumpPanel $panel): Div
    {
        $body = Div::tag()->class('yii-debug-dump-body');

        if ($row->trace === []) {
            return $body->html($row->message);
        }

        $items = array_map(
            static fn(array $frame): Li => Li::tag()->html($panel->getTraceLine($frame)),
            $row->trace,
        );

        return $body->html($row->message . Ul::tag()->class('yii-debug-trace')->html(...$items)->render());
    }

    /**
     * Renders the dump card head (`#index` badge, optional type badge, meta line with time and trace label).
     */
    private static function renderHead(DumpRow $row, int $index): Header
    {
        [$typeKey, $typeLabel] = self::sniffType($row->message);

        $headChildren = [
            Span::tag()
                ->addAriaAttribute('hidden', 'true')
                ->class('yii-debug-dump-index')
                ->content('#' . ($index + 1)),
        ];

        if ($typeLabel !== '') {
            $headChildren[] = Span::tag()
                ->addDataAttribute('type', $typeKey)
                ->class('yii-debug-dump-type')
                ->content($typeLabel);
        }

        $headChildren[] = Span::tag()
            ->class('yii-debug-dump-meta')
            ->html(...self::renderMeta($row));

        return Header::tag()
            ->class('yii-debug-dump-card-head')
            ->html(...$headChildren);
    }

    /**
     * Renders the meta line span children: the formatted time and the truncated trace location, when present.
     *
     * @return list<Span>
     */
    private static function renderMeta(DumpRow $row): array
    {
        $children = [];

        $timeStr = self::formatTime($row->time);

        if ($timeStr !== '') {
            $children[] = Span::tag()
                ->class('yii-debug-dump-time')
                ->content($timeStr);
        }

        [$file, $line] = self::firstFrame($row->trace);

        if ($file !== '') {
            $suffix = ($line !== null && $line > 0) ? ':' . $line : '';
            $children[] = Span::tag()
                ->class('yii-debug-dump-trace')
                ->content(basename($file) . $suffix)
                ->title($file . $suffix);
        }

        return $children;
    }

    /**
     * Sniffs a dump payload type from PHP's `highlight_string()` output.
     *
     * Decodes HTML entities so the first payload character (`[`, `'`, `"`, digit, identifier) classifies the dumped
     * value. A miss just hides the badge; never blocks render.
     *
     * @return array{0: string, 1: string} `[typeKey, typeLabel]` (both empty when the type cannot be determined).
     */
    private static function sniffType(string $message): array
    {
        $plain = html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $payload = ltrim((string) preg_replace('/^\s*<\?php\s*/', '', $plain));

        if ($payload === '') {
            return ['', ''];
        }

        $first = $payload[0];

        if ($first === '[') {
            return ['array', 'array'];
        }

        if ($first === "'" || $first === '"') {
            return ['string', 'string'];
        }

        if (preg_match('/^([A-Za-z_][A-Za-z0-9_\\\\]*)/', $payload, $m) === 1) {
            $name = $m[1];

            $lower = strtolower($name);

            if (in_array($lower, ['true', 'false'], true)) {
                return ['bool', 'bool'];
            }

            if ($lower === 'null') {
                return ['null', 'null'];
            }

            return ['object', $name];
        }

        if (preg_match('/^-?\d/', $payload) === 1) {
            return ['number', 'number'];
        }

        return ['', ''];
    }
}
