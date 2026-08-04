<?php

declare(strict_types=1);

namespace yii\debug\widgets\phpinfo;

/**
 * Top-level typed view-model for the phpinfo page.
 */
final readonly class PhpInfoView
{
    public function __construct(
        /**
         * Overview hero sections ('PHP version' first with the 'PHP_VERSION' headline, then Build / Configuration /
         * Capabilities / Streams).
         *
         * @var list<PhpInfoSection>
         */
        public array $sections,
        /**
         * TOC entries in display order (Overview first, then one entry per phpinfo module heading).
         *
         * @var list<PhpInfoTocEntry>
         */
        public array $tocEntries,
        /**
         * Small modules summarized in the Overview because they expose only one or two informational values and no
         * directives, statistics, notes, or other tables.
         *
         * @var list<PhpInfoCompactModule>
         */
        public array $compactModules,
        /**
         * Modules HTML produced by {@see phpinfo()} (already wrapped in `<section>` chrome by the normalizer); empty
         * string when no module heading is present in the output.
         */
        public string $modulesHtml,
        /**
         * Configure Command value (multi-line shell flags); empty when not reported by {@see phpinfo()}.
         */
        public string $configureCommand,
    ) {}
}
