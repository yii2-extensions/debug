<?php

declare(strict_types=1);

namespace yii\debug;

use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Toolbar\{ToolbarData, ToolbarItem, ToolbarPanel};

use function array_is_list;
use function array_replace;
use function is_array;

/**
 * Maps Yii2 panel envelopes to the shared typed toolbar contract while retaining custom-panel fields.
 *
 * Start from {@see create()} with the values the toolbar cannot render without, then layer the optional chrome through
 * the immutable `with*()` methods, which mirror {@see ToolbarData}. {@see map()} closes the chain.
 */
final readonly class ToolbarDataMapper
{
    /**
     * @param ToolbarData $data Payload enriched by the `with*()` methods and serialized by {@see map()}.
     */
    private function __construct(private ToolbarData $data) {}

    /**
     * Creates a mapper for a capture, ready for immutable enrichment.
     *
     * @param string $tag Tag of the capture the toolbar links to.
     * @param string $title Title of the captured request.
     *
     * @return self Mapper carrying only the mandatory toolbar identity.
     */
    public static function create(string $tag, string $title): self
    {
        return new self(ToolbarData::create($tag, $title));
    }

    /**
     * Creates the JSON-ready toolbar payload.
     *
     * Panels following the documented `items` schema are normalized through the Debug Core DTOs. A custom panel using
     * a free-form envelope remains untouched except for the `id`, `title`, and `url` defaults. Any extension fields
     * attached to an otherwise typed panel or item are merged back after DTO serialization.
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
    public function map(array $panels): array
    {
        $typedPanels = [];
        $compatiblePanels = [];

        foreach ($panels as $id => $panel) {
            if (!$panel->isVisible()) {
                continue;
            }

            $original = $panel->getToolbarData();

            if ($original === []) {
                continue;
            }

            $original['id'] ??= $id;
            $original['title'] ??= $panel->getName();
            $original['url'] ??= $panel->getUrl();

            $typed = self::panel($original);

            if ($typed === null) {
                $compatiblePanels[] = $original;

                continue;
            }

            $typedPanels[] = $typed;
            $compatiblePanels[] = self::mergePanelExtensions($original, $typed->jsonSerialize());
        }

        $data = $this->data->withPanels($typedPanels)->jsonSerialize();

        // The outer metadata always comes from the portable DTO. Only the panel list needs a compatibility lane for
        // custom extensions that use free-form envelopes.
        $data['items'] = $compatiblePanels;

        return $data;
    }

    /**
     * Returns a copy carrying the brand assets and the version labels.
     *
     * @param string|null $logo Logo as a data URI, or `null` to fall back.
     * @param string|null $logoFallback Logo used when the primary one is unavailable, or `null` for none.
     * @param string|null $phpVersion PHP version label, or `null` when unavailable.
     * @param string|null $yiiVersion Yii version label, or `null` when unavailable.
     *
     * @return self Mapper with the branding applied.
     */
    public function withBranding(
        string|null $logo,
        string|null $logoFallback = null,
        string|null $phpVersion = null,
        string|null $yiiVersion = null,
    ): self {
        return new self(
            $this->data->withBranding($logo, $logoFallback, $phpVersion, $yiiVersion),
        );
    }

    /**
     * Returns a copy carrying the debugger navigation URLs.
     *
     * An omitted configuration URL falls back to the history URL, which Debug Core requires to be non-`null`.
     *
     * @param string $indexUrl URL of the history page.
     * @param string|null $configUrl URL of the configuration panel, or `null` to reuse the history URL.
     * @param string|null $phpInfoUrl URL of the `phpinfo()` page, or `null` when disabled.
     *
     * @return self Mapper with the navigation applied.
     */
    public function withNavigation(
        string $indexUrl,
        string|null $configUrl = null,
        string|null $phpInfoUrl = null,
    ): self {
        return new self(
            $this->data->withNavigation($indexUrl, $configUrl ?? $indexUrl, $phpInfoUrl),
        );
    }

    /**
     * Returns a copy carrying the drawer presentation settings.
     *
     * @param string $position Edge the toolbar docks to.
     * @param int $defaultHeight Collapsed toolbar height, in pixels.
     * @param string $iconBaseUrl Base URL the panel icons resolve against.
     *
     * @return self Mapper with the presentation applied.
     */
    public function withPresentation(string $position, int $defaultHeight, string $iconBaseUrl = ''): self
    {
        return new self(
            $this->data->withPresentation($position, $defaultHeight, $iconBaseUrl),
        );
    }

    /**
     * Merges fields unknown to Debug Core back into a normalized panel and its individual item envelopes.
     *
     * @param array<string, mixed> $original Original panel envelope.
     * @param array<string, mixed> $typed DTO-serialized panel envelope.
     *
     * @return array<string, mixed> Typed envelope with extension fields retained.
     */
    private static function mergePanelExtensions(array $original, array $typed): array
    {
        $merged = array_replace($original, $typed);

        $originalItems = $original['items'] ?? null;
        $typedItems = $typed['items'] ?? null;

        if (!is_array($originalItems) || !is_array($typedItems)) {
            return $merged;
        }

        $mergedItems = $typedItems;

        foreach ($typedItems as $index => $typedItem) {
            $originalItem = $originalItems[$index] ?? null;

            if (is_array($originalItem) && is_array($typedItem)) {
                $mergedItems[$index] = array_replace($originalItem, $typedItem);
            }
        }

        $merged['items'] = $mergedItems;

        return $merged;
    }

    /**
     * Narrows an optional panel field to the nullable string required by the shared DTO.
     *
     * @param array<array-key, mixed> $data Original envelope.
     * @param string $key Field to read from the envelope.
     *
     * @return string|null Field as a string, or `null` when it is absent or not stringable.
     */
    private static function optionalString(array $data, string $key): string|null
    {
        return Coerce::stringOrNull($data[$key] ?? null);
    }

    /**
     * Returns a typed panel when the original envelope follows the portable schema.
     *
     * @param array<string, mixed> $data Original panel envelope.
     *
     * @return ToolbarPanel|null Typed panel, or `null` when the envelope does not follow the portable schema.
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

            $items[] = ToolbarItem::create($value)
                ->withLabel(self::optionalString($rawItem, 'label'))
                ->withIcon(self::optionalString($rawItem, 'icon'))
                ->withStatus(self::optionalString($rawItem, 'status') ?? 'default')
                ->withTitle(self::optionalString($rawItem, 'title'))
                ->withUrl(self::optionalString($rawItem, 'url'))
                ->withId(self::optionalString($rawItem, 'id'));
        }

        $id = Coerce::stringOrNull($data['id'] ?? null);
        $title = Coerce::stringOrNull($data['title'] ?? null);

        if ($id === null || $title === null) {
            return null;
        }

        return ToolbarPanel::create($id, $title)
            ->withUrl(self::optionalString($data, 'url'))
            ->withIcon(self::optionalString($data, 'icon'))
            ->withItems($items);
    }
}
