<?php

declare(strict_types=1);

namespace yii\debug\widgets\sidebar;

/**
 * Top-level typed view-model for the debugger sidebar partial.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class SidebarView
{
    public function __construct(
        /**
         * Snapshot card view-model; `null` when the manifest is empty so the card section is skipped entirely.
         */
        public SidebarSnapshot|null $snapshot,
        /**
         * Panel navigation entries in display order (History first, then panels except config).
         *
         * @var list<SidebarNavItem>
         */
        public array $navItems,
    ) {}
}
