<?php

declare(strict_types=1);

namespace yii\debug\panels\user;

/**
 * Typed view-model for one section in the user-identity card (`Identity` / `Security` / `Timestamps` / `Other`).
 */
final readonly class UserIdentitySection
{
    public function __construct(
        /**
         * Section label shown in the section header (`Identity`, `Security`, ...).
         */
        public string $label,
        /**
         * SVG icon glyph emitted into the section header chip.
         */
        public string $icon,
        /**
         * Attribute rows rendered under the section header.
         *
         * @var list<UserAttribute>
         */
        public array $attributes,
    ) {}
}
