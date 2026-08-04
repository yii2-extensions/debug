<?php

declare(strict_types=1);

namespace yii\debug\widgets\phpinfo;

/**
 * Compact phpinfo module surfaced in the Overview instead of receiving an almost-empty standalone panel.
 */
final readonly class PhpInfoCompactModule
{
    public function __construct(
        /**
         * Module title reported by {@see phpinfo()}.
         */
        public string $title,
        /**
         * Stable deep-link slug retained when the standalone panel is summarized in the Overview.
         */
        public string $slug,
        /**
         * The module's one or two informational values.
         *
         * @var list<PhpInfoTile>
         */
        public array $tiles,
    ) {}
}
