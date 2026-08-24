<?php

declare(strict_types=1);

namespace yii\debug\widgets\sidebar;

use PHPForge\Debug\View\Sidebar\{SidebarNavItem as CoreSidebarNavItem, SidebarView as CoreSidebarView};

use function array_map;

/**
 * Top-level typed view-model for the debugger sidebar partial.
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

    /**
     * Converts the backward-compatible Yii view-model wrappers to the portable Debug Core view-model.
     */
    public function toCore(): CoreSidebarView
    {
        return new CoreSidebarView(
            snapshot: $this->snapshot?->toCore(),
            navItems: array_map(
                static fn(SidebarNavItem $item): CoreSidebarNavItem => $item->toCore(),
                $this->navItems,
            ),
        );
    }
}
