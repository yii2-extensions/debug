<?php

declare(strict_types=1);

namespace yii\debug\panels\user;

/**
 * Typed view-model for the user-identity hero card: monogram, name, email, status pill, and id pill.
 *
 * Pre-resolves the avatar monogram, the status label (`Active` / `Banned` / `Inactive` / custom), and the matching CSS
 * variant, so the renderer is purely formatting.
 */
final readonly class UserIdentityHero
{
    public function __construct(
        /**
         * Display username; falls back to `Unknown user` when the identity has no `username` or `name`.
         */
        public string $username,
        /**
         * User email, or `''` when not present on the identity.
         */
        public string $email,
        /**
         * Stringified user id, or `''` when not present on the identity.
         *
         * Rendered next to the status pill as `ID #<value>`.
         */
        public string $idValue,
        /**
         * Uppercase one-letter monogram for the avatar (for example, `A` for `admin`), falling back to `?` when
         * neither username nor email is available.
         */
        public string $monogram,
        /**
         * Status display label (`Active`, `Banned`, `Inactive`, custom value, or `Unknown`), or `''` when the identity
         * does not carry a status field.
         */
        public string $statusLabel,
        /**
         * CSS variant token (`success` / `danger` / `muted`) controlling the status pill tint.
         */
        public string $statusVariant,
    ) {}
}
