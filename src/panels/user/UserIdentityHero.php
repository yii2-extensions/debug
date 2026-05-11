<?php

declare(strict_types=1);

namespace yii\debug\panels\user;

/**
 * Typed view-model for the user-identity hero card (monogram + name + email + status pill + id pill).
 *
 * Pre-resolves the avatar monogram, the status label ('Active' / 'Banned' / 'Inactive' / custom) and the matching CSS
 * variant so the renderer is purely formatting.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class UserIdentityHero
{
    public function __construct(
        /**
         * Display username; falls back to 'Unknown user' when the identity has no 'username'/'name'.
         */
        public string $username,
        /**
         * User email; empty when not present on the identity.
         */
        public string $email,
        /**
         * Stringified user id; empty when not present on the identity. Rendered next to the status pill as
         * 'ID #<value>'.
         */
        public string $idValue,
        /**
         * Uppercase one-letter monogram for the avatar ('A' for 'admin'). Falls back to '?' when neither
         * username nor email is available.
         */
        public string $monogram,
        /**
         * Status display label ('Active', 'Banned', 'Inactive', custom value or 'Unknown'). Empty when the
         * identity does not carry a status field.
         */
        public string $statusLabel,
        /**
         * CSS variant token ('success' / 'danger' / 'muted') controlling the status pill tint.
         */
        public string $statusVariant,
    ) {}
}
