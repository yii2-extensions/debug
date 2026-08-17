<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Panel\Inertia\InertiaSnapshot;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\Panel;

use function is_a;
use function is_array;
use function is_object;

/**
 * Renders the Inertia page object captured by the Inertia collector.
 *
 * Presents the server-side page payload (component, props, URL, asset version) and the `X-Inertia-*` negotiation
 * headers, so partial reloads and version conflicts can be inspected per capture. The panel enables itself only when
 * the application wires the `yii2-extensions/inertia` `Manager` under the `inertia` component id; data acquisition
 * lives in {@see \yii\debug\collectors\InertiaCollector}.
 */
class InertiaPanel extends Panel
{
    protected const string ICON = 'inertia';
    protected const string NAME = 'Inertia';

    /**
     * Application component id under which the Inertia manager is registered.
     */
    private const string COMPONENT_ID = 'inertia';
    /**
     * Manager FQCN from `yii2-extensions/inertia`, referenced as a string to avoid a hard package dependency.
     */
    private const string MANAGER_CLASS = 'yii\inertia\Manager';

    private InertiaSnapshot|null $snapshot = null;

    /**
     * Renders the detail view from the captured page payload.
     */
    #[Override]
    public function getDetail(): string
    {
        return Yii::$app->view->render('panels/inertia/detail', ['panel' => $this], $this);
    }

    /**
     * Returns the `X-Inertia-Location` redirect target captured for the request, or `null` when there was none.
     */
    public function getLocation(): string|null
    {
        return $this->snapshot?->location;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getSnapshotData(): array
    {
        return $this->snapshot?->data() ?? [];
    }

    /**
     * Returns the response status code captured alongside the Inertia page.
     */
    public function getStatusCode(): int
    {
        return $this->snapshot === null ? 0 : $this->snapshot->statusCode;
    }

    /**
     * Returns whether the loaded capture carries Inertia activity (a rendered page or an `X-Inertia` XHR).
     *
     * Keeps the sidebar entry per-capture: plain requests (assets, JSON endpoints, redirects) do not list the panel.
     */
    #[Override]
    public function hasContent(): bool
    {
        $data = $this->getSnapshotData();

        $requestHeaders = $data['requestHeaders'] ?? null;

        return is_array($data['page'] ?? null)
            || (is_array($requestHeaders) && isset($requestHeaders['X-Inertia']));
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->snapshot = InertiaSnapshot::fromArray($payload, "$.panels.{$this->id}");
    }

    /**
     * Returns whether the application wires the Inertia manager under the `inertia` component id.
     */
    #[Override]
    public function isEnabled(): bool
    {
        try {
            $component = Yii::$app->get(self::COMPONENT_ID);
        } catch (InvalidConfigException) {
            return false;
        }

        return is_object($component) && is_a($component, self::MANAGER_CLASS);
    }

    /**
     * Returns the toolbar chip carrying the rendered component name, or `[]` when no page was captured.
     */
    #[Override]
    protected function getToolbarItems(): array
    {
        $data = $this->getSnapshotData();

        $page = $data['page'] ?? null;

        $component = is_array($page) ? Coerce::string($page['component'] ?? null) : '';

        if ($component === '') {
            return [];
        }

        return [
            [
                'title' => 'Inertia component',
                'value' => $component,
            ],
        ];
    }
}
