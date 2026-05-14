<?php

declare(strict_types=1);

namespace yii\debug\panels\user;

/**
 * Typed top-level view-model for the user-identity card (hero + per-section attribute lists).
 */
final readonly class UserIdentityView
{
    public function __construct(
        /**
         * Hero card view-model.
         */
        public UserIdentityHero $hero,
        /**
         * Section view-models in display order; empty sections are filtered out by the normalizer.
         *
         * @var list<UserIdentitySection>
         */
        public array $sections,
    ) {}
}
