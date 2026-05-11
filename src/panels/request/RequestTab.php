<?php

declare(strict_types=1);

namespace yii\debug\panels\request;

/**
 * Typed view-model for one tab in the Request panel detail view.
 *
 * Each tab carries a navigation label and the sections rendered when that tab is active. The first tab in the list is
 * marked active by the renderer; the remaining ones are aria-hidden until clicked.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class RequestTab
{
    public function __construct(
        /**
         * Navigation label shown in the tab strip ('Parameters', 'Headers', 'Session', 'Server').
         */
        public string $label,
        /**
         * Sections rendered when this tab is active, in display order.
         *
         * @var list<RequestSection>
         */
        public array $sections,
    ) {}
}
