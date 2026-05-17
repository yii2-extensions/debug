<?php

declare(strict_types=1);

namespace yii\debug\panels\user;

/**
 * Typed view-model for one user-identity attribute row.
 *
 * Carries the display value, the human-readable label, and the kind discriminator that controls how the renderer
 * formats the value (plain text, security reveal button, timestamp with relative + absolute parts, or the empty `—`
 * placeholder).
 */
final readonly class UserAttribute
{
    /**
     * Rendering kind: empty `—` placeholder.
     */
    public const string KIND_EMPTY = 'empty';
    /**
     * Rendering kind: plain text value.
     */
    public const string KIND_PLAIN = 'plain';
    /**
     * Rendering kind: masked value behind a reveal button.
     */
    public const string KIND_SECURITY = 'security';
    /**
     * Rendering kind: timestamp pair (relative + absolute).
     */
    public const string KIND_TIMESTAMP = 'timestamp';

    public function __construct(
        /**
         * Raw attribute key (for example, `username`, `created_at`, `auth_key`).
         */
        public string $key,
        /**
         * Human-readable label resolved through the {@see \yii\base\Model::attributeLabels()} map, or computed as a
         * title-cased version of `$key`.
         */
        public string $label,
        /**
         * Display string with surrounding {@see \yii\helpers\VarDumper} quotes already stripped, or `''` when the raw
         * value was `null` or empty.
         */
        public string $displayValue,
        /**
         * Rendering kind; one of the `KIND_*` constants.
         */
        public string $kind,
        /**
         * Relative timestamp string (for example, `28 d ago`); only populated when `$kind === KIND_TIMESTAMP`.
         */
        public string $timestampRel = '',
        /**
         * Absolute timestamp string (for example, `Apr 13, 2026 · 14:19`); only populated when
         * `$kind === KIND_TIMESTAMP`.
         */
        public string $timestampAbs = '',
    ) {}
}
