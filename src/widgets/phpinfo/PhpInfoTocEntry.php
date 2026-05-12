<?php

declare(strict_types=1);

namespace yii\debug\widgets\phpinfo;

/**
 * Typed view-model for one entry in the phpinfo TOC sidebar.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class PhpInfoTocEntry
{
    public function __construct(
        /**
         * Display title ('Overview', 'apcu', 'Core', ...).
         */
        public string $title,
        /**
         * Anchor slug ('phpinfo-overview', 'phpinfo-apcu', ...) consumed by the TOC anchor and the section id.
         */
        public string $slug,
    ) {}
}
