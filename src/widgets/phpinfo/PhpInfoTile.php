<?php

declare(strict_types=1);

namespace yii\debug\widgets\phpinfo;

/**
 * Typed view-model for one tile in a phpinfo Overview section.
 *
 * The `$kind` discriminator drives the renderer's branch: enabled/disabled pill, comma-separated path or token list,
 * single shortened path, or plain code text.
 */
final readonly class PhpInfoTile
{
    public const string KIND_PATH = 'path';
    public const string KIND_PATH_LIST = 'path-list';
    public const string KIND_PILL_MUTED = 'pill-muted';
    public const string KIND_PILL_SUCCESS = 'pill-success';
    public const string KIND_TEXT = 'text';
    public const string KIND_TOKEN_LIST = 'token-list';

    public function __construct(
        /**
         * Tile label rendered in the `<dt>` cell ('SAPI', 'Memory limit', ...).
         */
        public string $label,
        /**
         * Display value already prepared for the rendering branch indicated by `$kind`.
         */
        public string $displayValue,
        /**
         * Full original value (used as the title tooltip when the display value was shortened).
         */
        public string $rawValue,
        /**
         * Rendering kind. See the `KIND_*` constants.
         */
        public string $kind,
        /**
         * Path tokens for {@see KIND_PATH_LIST} (already with {@see basename()} applied for display) or short tokens
         * for {@see KIND_TOKEN_LIST}. Empty list for the other kinds.
         *
         * @var list<PhpInfoToken>
         */
        public array $tokens = [],
    ) {}
}
