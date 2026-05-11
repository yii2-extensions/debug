<?php

declare(strict_types=1);

namespace yii\debug\panels\user;

/**
 * Typed view-model for one user-identity attribute row.
 *
 * Encapsulates the loose string value rendered by the user-identity view, the human-readable label, and the kind
 * discriminator that controls how the renderer formats the value (plain text, security reveal button, timestamp
 * with relative + absolute parts, or empty `—` placeholder).
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class UserAttribute
{
    public const string KIND_EMPTY = 'empty';
    public const string KIND_PLAIN = 'plain';
    public const string KIND_SECURITY = 'security';
    public const string KIND_TIMESTAMP = 'timestamp';

    public function __construct(
        /**
         * Raw attribute key ('username', 'created_at', 'auth_key', ...).
         */
        public string $key,
        /**
         * Human-readable label resolved through the {@see \yii\base\Model::attributeLabels()} map or computed as a
         * title-cased version of `$key`.
         */
        public string $label,
        /**
         * Display string with surrounding {@see \yii\helpers\VarDumper} quotes already stripped; empty when the raw
         * value was `null` or empty.
         */
        public string $displayValue,
        /**
         * Rendering kind. See the 'KIND_*' constants.
         */
        public string $kind,
        /**
         * Relative timestamp string ('28 d ago'); only populated when '$kind === KIND_TIMESTAMP'.
         */
        public string $timestampRel = '',
        /**
         * Absolute timestamp string ('Apr 13, 2026 · 14:19'); only populated when '$kind === KIND_TIMESTAMP'.
         */
        public string $timestampAbs = '',
    ) {}
}
