<?php

declare(strict_types=1);

namespace yii\debug\panels\request;

/**
 * Typed view-model for one tab in the Request panel detail view.
 *
 * Each tab carries a navigation label and the sections rendered when that tab is active. The first tab in the list is
 * marked active by the renderer; the remaining ones are aria-hidden until clicked.
 */
final readonly class RequestTab
{
    public function __construct(
        /**
         * Navigation label shown in the tab strip (`'Parameters'`, `'Headers'`, `'Session'`, `'Server'`).
         */
        public string $label,
        /**
         * @var list<RequestSection> Sections rendered when this tab is active, in display order.
         */
        public array $sections,
    ) {}
}
