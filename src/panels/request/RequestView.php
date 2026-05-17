<?php

declare(strict_types=1);

namespace yii\debug\panels\request;

/**
 * Top-level typed view-model for the Request panel detail view.
 *
 * Bundles the {@see RequestHero} (hero header) and the list of {@see RequestTab}s rendered as the tab strip below it.
 *
 * The view consumes only this DTO; every defensive {@see is_array()} / {@see is_string()} narrowing happens once in
 * {@see RequestDataNormalizer::fromPanelData()}.
 */
final readonly class RequestView
{
    public function __construct(
        /**
         * Hero header view-model.
         */
        public RequestHero $hero,
        /**
         * @var list<RequestTab> Tab view-models in display order; the first tab is rendered active by the section
         * renderer.
         */
        public array $tabs,
    ) {}
}
