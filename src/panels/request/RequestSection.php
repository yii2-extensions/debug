<?php

declare(strict_types=1);

namespace yii\debug\panels\request;

/**
 * Typed view-model for one name/value section rendered in the Request panel detail view.
 *
 * A section is the unit consumed by {@see RequestSectionRenderer::renderSection()}: it groups the section title, the
 * optional filter affordance and the name → value pairs that drive the rendered `<table>` (or the empty-state fallback
 * when `entries` is empty).
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class RequestSection
{
    public function __construct(
        /**
         * Section heading shown at the top of the rendered table.
         */
        public string $caption,
        /**
         * Name → value entries that populate the table rows. Keys are coerced to strings during rendering and values
         * are dumped via {@see \yii\helpers\VarDumper::dumpAsString()} for a stable, syntax-aware presentation.
         *
         * @var array<int|string, mixed>
         */
        public array $entries,
        /**
         * When `true` the renderer emits a search input next to the caption and wraps the table in a filter target so
         * the developer can narrow long tables (Session, Server, Headers).
         */
        public bool $filterable = false,
    ) {}
}
