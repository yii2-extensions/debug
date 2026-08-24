<?php

declare(strict_types=1);

namespace yii\debug;

use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Toolbar\{ToolbarData, ToolbarItem, ToolbarPanel};

use function array_is_list;
use function array_replace;
use function is_array;

/**
 * Maps Yii2 panel envelopes to the shared typed toolbar contract while retaining legacy custom-panel fields.
 */
final readonly class ToolbarDataMapper
{
    /**
     * Creates the JSON-ready toolbar payload.
     *
     * Panels following the documented `items` schema are normalized through the Debug Core DTOs. A custom panel using
     * the former free-form envelope remains untouched except for the historical `id`, `title`, and `url` defaults. Any
     * extension fields attached to an otherwise typed panel or item are merged back after DTO serialization.
     *
     * @param array<string, Panel> $panels Registered Yii2 panels in toolbar order.
     *
     * @return array{
     *   configUrl: string,
     *   defaultHeight: int,
     *   iconBaseUrl: string,
     *   indexUrl: string,
     *   items: list<array<string, mixed>>,
     *   logo: string|null,
     *   logoFallback: string|null,
     *   phpInfoUrl: string|null,
     *   phpVersion: string|null,
     *   position: string,
     *   tag: string,
     *   title: string,
     *   yiiVersion: string|null,
     * } Payload consumed by the shared toolbar runtime.
     */
    public function map(
        string $tag,
        string $title,
        string $indexUrl,
        string|null $configUrl,
        array $panels,
        string $position = 'bottom',
        int $defaultHeight = 50,
        string $iconBaseUrl = '',
        string|null $logo = null,
        string|null $logoFallback = null,
        string|null $phpInfoUrl = null,
        string|null $phpVersion = null,
        string|null $yiiVersion = null,
    ): array {
        $typedPanels = [];
        $compatiblePanels = [];

        foreach ($panels as $id => $panel) {
            $legacy = $panel->getToolbarData();

            if ($legacy === []) {
                continue;
            }

            $legacy['id'] ??= $id;
            $legacy['title'] ??= $panel->getName();
            $legacy['url'] ??= $panel->getUrl();

            $typed = self::panel($legacy);

            if ($typed === null) {
                $compatiblePanels[] = $legacy;

                continue;
            }

            $typedPanels[] = $typed;
            $compatiblePanels[] = self::mergePanelExtensions($legacy, $typed->jsonSerialize());
        }

        $data = (new ToolbarData(
            tag: $tag,
            title: $title,
            indexUrl: $indexUrl,
            configUrl: $configUrl ?? $indexUrl,
            items: $typedPanels,
            position: $position,
            defaultHeight: $defaultHeight,
            iconBaseUrl: $iconBaseUrl,
            logo: $logo,
            logoFallback: $logoFallback,
            phpInfoUrl: $phpInfoUrl,
            phpVersion: $phpVersion,
            yiiVersion: $yiiVersion,
        ))->jsonSerialize();

        // The outer metadata always comes from the portable DTO. Only the panel list needs a compatibility lane for
        // legacy extensions that predate the structured item contract.
        $data['items'] = $compatiblePanels;

        return $data;
    }

    /**
     * Merges fields unknown to Debug Core back into a normalized panel and its individual item envelopes.
     *
     * @param array<string, mixed> $legacy Original panel envelope.
     * @param array<string, mixed> $typed DTO-serialized panel envelope.
     *
     * @return array<string, mixed> Typed envelope with extension fields retained.
     */
    private static function mergePanelExtensions(array $legacy, array $typed): array
    {
        $merged = array_replace($legacy, $typed);

        $legacyItems = $legacy['items'] ?? null;
        $typedItems = $typed['items'] ?? null;

        if (!is_array($legacyItems) || !is_array($typedItems)) {
            return $merged;
        }

        $mergedItems = $typedItems;

        foreach ($typedItems as $index => $typedItem) {
            $legacyItem = $legacyItems[$index] ?? null;

            if (is_array($legacyItem) && is_array($typedItem)) {
                $mergedItems[$index] = array_replace($legacyItem, $typedItem);
            }
        }

        $merged['items'] = $mergedItems;

        return $merged;
    }

    /**
     * Narrows an optional legacy field to the nullable string required by the shared DTO.
     *
     * @param array<array-key, mixed> $data Legacy envelope.
     */
    private static function optionalString(array $data, string $key): string|null
    {
        return Coerce::stringOrNull($data[$key] ?? null);
    }

    /**
     * Returns a typed panel when the legacy envelope follows the portable schema.
     *
     * @param array<string, mixed> $data Legacy panel envelope.
     */
    private static function panel(array $data): ToolbarPanel|null
    {
        $rawItems = $data['items'] ?? null;

        if (!is_array($rawItems) || !array_is_list($rawItems)) {
            return null;
        }

        $items = [];

        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                return null;
            }

            $value = Coerce::stringOrNull($rawItem['value'] ?? null);

            if ($value === null) {
                return null;
            }

            $items[] = new ToolbarItem(
                value: $value,
                label: self::optionalString($rawItem, 'label'),
                icon: self::optionalString($rawItem, 'icon'),
                status: self::optionalString($rawItem, 'status') ?? 'default',
                title: self::optionalString($rawItem, 'title'),
                url: self::optionalString($rawItem, 'url'),
                id: self::optionalString($rawItem, 'id'),
            );
        }

        $id = Coerce::stringOrNull($data['id'] ?? null);
        $title = Coerce::stringOrNull($data['title'] ?? null);

        if ($id === null || $title === null) {
            return null;
        }

        return new ToolbarPanel(
            id: $id,
            title: $title,
            url: self::optionalString($data, 'url'),
            icon: self::optionalString($data, 'icon'),
            items: $items,
        );
    }
}
