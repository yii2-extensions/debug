<?php

declare(strict_types=1);

namespace yii\debug;

use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Toolbar\{ToolbarData, ToolbarItem, ToolbarPanel};
use yii\debug\exception\Message;

use function array_is_list;
use function is_array;

/**
 * Maps Yii2 panel envelopes to the shared typed toolbar contract.
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
     * Every panel is serialized through the Debug Core DTOs, so fields outside the `id`, `title`, `url`, `icon`, and
     * `items` schema are dropped. A panel whose envelope breaks that schema renders a single error chip carrying
     * {@see Message::TOOLBAR_ENVELOPE_INVALID} as its tooltip, keeping the failure contained to its own chip.
     *
     * Chips are emitted in the order the module registered the panels, which the shared registration policy already
     * resolved, so the toolbar mirrors the sidebar grouping. Every panel the module registry classifies as an extension,
     * and every panel it does not list (a raw JSON fallback), carries `extension: true`, so the shared toolbar groups it
     * under its Extensions menu; built-in panels omit the key and stay inline.
     *
     * @param array<string, Panel> $panels Registered Yii2 panels keyed by ID, in display order.
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

        foreach ($panels as $id => $panel) {
            if (!$panel->isVisible()) {
                continue;
            }

            $envelope = $panel->getToolbarData();

            if ($envelope === []) {
                continue;
            }

            $envelope['id'] ??= $id;
            $envelope['title'] ??= $panel->getName();
            $envelope['url'] ??= $panel->getUrl();

            $typedPanels[] = self::panel($id, $panel, $envelope)
                ->withExtension($panel->module?->getPanelRegistry()->get($id)->extension ?? true);
        }

        return $this->data->withPanels($typedPanels)->jsonSerialize();
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
     * Builds the diagnostic chip rendered in place of an envelope that breaks the typed toolbar contract.
     *
     * Mirrors the error shape of {@see Panel::getToolbarData()}, so a third-party panel failure reads like a capture
     * failure instead of breaking the toolbar.
     *
     * @param string $id Panel ID under which the panel is registered.
     * @param Panel $panel Panel whose envelope was rejected.
     * @param string $field Envelope field that broke the contract.
     *
     * @return ToolbarPanel Panel carrying the single error chip.
     */
    private static function errorPanel(string $id, Panel $panel, string $field): ToolbarPanel
    {
        return ToolbarPanel::create($id, $panel->getName())
            ->withUrl($panel->getUrl())
            ->withItems(
                [
                    ToolbarItem::create('error')
                        ->withLabel($panel->getName())
                        ->withStatus('danger')
                        ->withTitle(Message::TOOLBAR_ENVELOPE_INVALID->getMessage($id, $field)),
                ],
            );
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
     * Narrows a panel envelope to the typed toolbar contract, falling back to an error chip when it breaks.
     *
     * @param string $id Panel ID under which the panel is registered.
     * @param Panel $panel Panel that produced the envelope.
     * @param array<string, mixed> $data Panel envelope, with the ID, title, and URL defaults already applied.
     *
     * @return ToolbarPanel Typed panel, or the diagnostic chip built by {@see errorPanel()}.
     */
    private static function panel(string $id, Panel $panel, array $data): ToolbarPanel
    {
        $rawItems = $data['items'] ?? null;

        if (!is_array($rawItems) || !array_is_list($rawItems)) {
            return self::errorPanel($id, $panel, 'items');
        }

        $items = [];

        foreach ($rawItems as $index => $rawItem) {
            if (!is_array($rawItem)) {
                return self::errorPanel($id, $panel, "items[{$index}]");
            }

            $value = Coerce::stringOrNull($rawItem['value'] ?? null);

            if ($value === null) {
                return self::errorPanel($id, $panel, "items[{$index}].value");
            }

            $items[] = ToolbarItem::create($value)
                ->withLabel(self::optionalString($rawItem, 'label'))
                ->withIcon(self::optionalString($rawItem, 'icon'))
                ->withStatus(self::optionalString($rawItem, 'status') ?? 'default')
                ->withTitle(self::optionalString($rawItem, 'title'))
                ->withUrl(self::optionalString($rawItem, 'url'))
                ->withId(self::optionalString($rawItem, 'id'));
        }

        $panelId = Coerce::stringOrNull($data['id'] ?? null);

        if ($panelId === null) {
            return self::errorPanel($id, $panel, 'id');
        }

        $title = Coerce::stringOrNull($data['title'] ?? null);

        if ($title === null) {
            return self::errorPanel($id, $panel, 'title');
        }

        return ToolbarPanel::create($panelId, $title)
            ->withUrl(self::optionalString($data, 'url'))
            ->withIcon(self::optionalString($data, 'icon'))
            ->withItems($items);
    }
}
