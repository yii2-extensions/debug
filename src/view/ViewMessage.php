<?php

declare(strict_types=1);

namespace yii\debug\view;

/**
 * Presentation text specific to this adapter, kept out of Debug Core because it names Yii2 concepts.
 *
 * Framework-neutral chrome text lives in {@see \PHPForge\Debug\View\ViewMessage} and the per-panel Debug Core catalogs;
 * only wording that would be wrong for another framework belongs here.
 */
enum ViewMessage: string
{
    /**
     * Toolbar metric counting the asset bundles registered during the request.
     */
    case ASSET_BUNDLE_COUNT = 'Number of asset bundles loaded';

    /**
     * Explanation shown above the panel comparison table, stating what the counts do and do not reveal.
     */
    case COMPARISON_COUNTS_SCOPE = 'Counts compare typed JSON leaf paths without rendering captured values. Open '
        . 'either panel for its redacted detail.';

    /**
     * Tooltip of the brand-bar action while no capture is available to open.
     */
    case CONFIG_ACTION_EMPTY = 'No requests captured yet';

    /**
     * Tooltip of the brand-bar action leading to the configuration page.
     */
    case CONFIG_ACTION_TOOLTIP = 'Open the Configuration panel';

    /**
     * Toolbar metric counting the values passed to `Yii::debug()` during the request.
     */
    case DUMP_COUNT = 'Number of dumped variables';

    /**
     * Headline of the Dump panel empty state.
     */
    case DUMP_EMPTY_HEADLINE = 'No variables dumped in this request';

    /**
     * Headline of the Events panel empty state.
     */
    case EVENT_EMPTY_HEADLINE = 'No events triggered in this request';

    /**
     * Accessible name of the event-class filter input.
     */
    case EVENT_FILTER_CLASS = 'Filter by event class';

    /**
     * Accessible name of the event-name filter input.
     */
    case EVENT_FILTER_NAME = 'Filter by event name';

    /**
     * Accessible name of the sender-class filter input.
     */
    case EVENT_FILTER_SENDER = 'Filter by sender';

    /**
     * Accessible name of the static-event filter input.
     */
    case EVENT_FILTER_STATIC = 'Filter by static events';

    /**
     * Tooltip of the sidebar entry leading to the request history.
     */
    case HISTORY_TOOLTIP = 'Browse all captured requests';

    /**
     * Tooltip of a sidebar panel entry that opens on the newest capture.
     */
    case PANEL_TOOLTIP_NEWEST = 'Open this panel on the newest request';

    /**
     * Tooltip of a sidebar panel entry while the history grid has no capture selected.
     */
    case PANEL_TOOLTIP_NO_SELECTION = 'Pick a request first';

    /**
     * Provenance label shown above the route inventory, naming the Yii2 component it was read from.
     */
    case ROUTE_INVENTORY_SOURCE = 'Current URL manager configuration';

    /**
     * Explanation of the Timeline empty state when the filtered spans carry no usable geometry.
     */
    case TIMELINE_UNAVAILABLE_FILTERED = 'The filtered spans cannot be positioned on this request timeline.';

    /**
     * Instructions announced to assistive technology above the user-switch grid.
     */
    case USER_SWITCH_INSTRUCTIONS = 'Press Enter or Space on a user row to switch identity.';

    /**
     * Accessible name of a user-switch row whose key cannot be named.
     */
    case USER_SWITCH_ROW_FALLBACK = 'Switch to selected user';
}
