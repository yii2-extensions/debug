<?php

declare(strict_types=1);

namespace yii\debug\panels\queue;

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Span, Strong};

use function abs;
use function count;
use function date;
use function explode;
use function implode;
use function number_format;
use function sprintf;

/**
 * Renders the column cells for the Queue panel grid view.
 *
 * Stateless static helpers; each public method takes a typed {@see JobRecord} (and, for `renderJobCell`, a per-row
 * detail URL builder) and returns a fully-rendered HTML string suitable for a Yii GridView column `value` callback.
 *
 * Usage example:
 * ```php
 * echo \yii\debug\panels\queue\QueueGridRenderer::renderStatusCell($record);
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class QueueGridRenderer
{
    /**
     * Maps each event type to a CSS modifier and a human label used by the status pill.
     *
     * @var array<string, array{variant: string, label: string}>
     */
    private const array EVENT_VARIANTS = [
        'push' => ['variant' => 'queued', 'label' => 'Queued'],
        'exec' => ['variant' => 'done', 'label' => 'Done'],
        'error' => ['variant' => 'failed', 'label' => 'Failed'],
    ];

    /**
     * Renders the attempt cell as `#N` for non-zero attempts, `—` otherwise.
     */
    public static function renderAttemptCell(JobRecord $record): string
    {
        if ($record->attempt === null || $record->attempt <= 0) {
            return '—';
        }

        return "#{$record->attempt}";
    }

    /**
     * Renders the component id cell (`'queue'` / `'queueRedis'` / ...). Plain text; the value is small enough that a
     * pill would only add visual noise.
     */
    public static function renderComponentCell(JobRecord $record): string
    {
        return $record->componentId;
    }

    /**
     * Renders the driver pill (`Sync` / `Database` / `Redis` / ...). Async drivers carry the `is-async` modifier so the
     * developer can spot at a glance which jobs ran in-process.
     */
    public static function renderDriverCell(JobRecord $record): string
    {
        if ($record->driverName === '') {
            return '';
        }

        $modifier = $record->isAsync ? 'is-async' : 'is-sync';

        return Span::tag()
            ->class("yii-debug-queue-driver yii-debug-queue-driver-{$modifier}")
            ->title($record->driverClass !== '' ? $record->driverClass : 'Unknown driver')
            ->content($record->driverName)
            ->render();
    }

    /**
     * Renders the duration cell; `X.X ms` for executed/errored events, `—` for the push-only events that did not carry
     * a duration.
     */
    public static function renderDurationCell(JobRecord $record): string
    {
        if ($record->duration === null) {
            return '—';
        }

        return number_format($record->duration * 1000, 1) . ' ms';
    }

    /**
     * Renders the job-id cell. Wraps the id in a monospaced link-styled span (matches the History panel's tag column)
     * so async-driver ids (UUIDs, hex strings, ...) read cleanly. Empty becomes `—` so the column never collapses to
     * whitespace and the dash makes it obvious the queue did not return an id at push time.
     */
    public static function renderIdCell(JobRecord $record): string
    {
        if ($record->jobId === '') {
            return '—';
        }

        return Span::tag()
            ->class('yii-debug-tag-link')
            ->content($record->jobId)
            ->render();
    }

    /**
     * Renders the job-class cell as a link to the dedicated detail page. The short class name shows in bold; the
     * namespace prefix renders muted underneath. The full FQCN sits in the `title` attribute for hover inspection.
     *
     * Click drills into the typed payload tree.
     */
    public static function renderJobCell(JobRecord $record, string $href): string
    {
        $shortName = self::shortName($record->jobClass);
        $namespace = self::namespacePart($record->jobClass);

        return Div::tag()
            ->class('yii-debug-queue-grid-job')
            ->html(
                A::tag()
                    ->class('yii-debug-queue-grid-job-link')
                    ->href($href)
                    ->title($record->jobClass !== '' ? $record->jobClass : 'Mixed payload')
                    ->html(Strong::tag()->content($shortName === '' ? '—' : $shortName)),
                $namespace !== ''
                    ? Span::tag()
                        ->class('yii-debug-queue-grid-job-namespace')
                        ->content("{$namespace}\\")
                    : '',
            )
            ->render();
    }

    /**
     * Renders the status pill (`Queued` / `Done` / `Failed`) for the captured event type.
     */
    public static function renderStatusCell(JobRecord $record): string
    {
        $variant = self::EVENT_VARIANTS[$record->eventType]['variant'] ?? 'queued';
        $label = self::EVENT_VARIANTS[$record->eventType]['label'] ?? 'Queued';

        return Span::tag()
            ->class("yii-debug-queue-status yii-debug-queue-status-{$variant}")
            ->content($label)
            ->render();
    }

    /**
     * Renders the time cell as `HH:MM:SS.mmm` derived from the captured `microtime` float.
     */
    public static function renderTimeCell(JobRecord $record): string
    {
        $seconds = (int) $record->time;
        $milliseconds = abs((int) (($record->time - $seconds) * 1000));

        return date('H:i:s.', $seconds) . sprintf('%03d', $milliseconds);
    }

    /**
     * Renders the time-to-reserve cell as `Ns` for non-zero TTRs, `—` otherwise.
     */
    public static function renderTtrCell(JobRecord $record): string
    {
        if ($record->ttr === null || $record->ttr <= 0) {
            return '—';
        }

        return "{$record->ttr}s";
    }

    /**
     * Returns the namespace prefix (without trailing backslash), or empty string when the FQCN has no namespace.
     */
    private static function namespacePart(string $fqcn): string
    {
        if ($fqcn === '') {
            return '';
        }

        $parts = explode('\\', $fqcn);

        if (count($parts) <= 1) {
            return '';
        }

        unset($parts[count($parts) - 1]);

        return implode('\\', $parts);
    }

    /**
     * Returns the trailing class name (without namespace), or empty string when the FQCN itself is empty.
     */
    private static function shortName(string $fqcn): string
    {
        if ($fqcn === '') {
            return '';
        }

        $parts = explode('\\', $fqcn);

        return $parts[count($parts) - 1] ?? '';
    }
}
