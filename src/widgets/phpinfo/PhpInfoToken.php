<?php

declare(strict_types=1);

namespace yii\debug\widgets\phpinfo;

/**
 * Typed view-model for one token inside a {@see PhpInfoTile::KIND_PATH_LIST} / {@see PhpInfoTile::KIND_TOKEN_LIST}
 * tile.
 */
final readonly class PhpInfoToken
{
    public function __construct(
        /**
         * Display text shown inside the `<code>` token (for path lists this is the basename only).
         */
        public string $label,
        /**
         * Full path / value surfaced in the token's {@see PhpInfoToken::$title} tooltip; empty when the label IS the
         * full value (short-token lists).
         */
        public string $title = '',
    ) {}
}
