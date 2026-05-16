<?php

declare(strict_types=1);

namespace yii\debug\widgets\phpinfo;

/**
 * Typed view-model for one section in the phpinfo Overview hero ('PHP version' / 'Build' / 'Configuration' /
 * 'Capabilities' / 'Streams').
 */
final readonly class PhpInfoSection
{
    public function __construct(
        /**
         * Eyebrow shown in the section header ('PHP version', 'Build', ...).
         */
        public string $eyebrow,
        /**
         * Non-empty tiles surfaced under the eyebrow (already filtered by the normalizer).
         *
         * @var list<PhpInfoTile>
         */
        public array $tiles,
        /**
         * Optional hero headline (the big 'PHP_VERSION' digits + 'php' mark surfaced only by the first section);
         * `null` for the regular sections.
         */
        public string|null $headline = null,
    ) {}
}
