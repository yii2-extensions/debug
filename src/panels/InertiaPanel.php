<?php

declare(strict_types=1);

namespace yii\debug\panels;

use JsonSerializable;
use Override;
use ReflectionException;
use ReflectionMethod;
use Yii;
use yii\base\{InvalidConfigException, View, ViewEvent};
use yii\debug\Panel;

use function array_keys;
use function is_a;
use function is_array;
use function is_object;
use function is_string;
use function json_decode;
use function json_encode;

/**
 * Captures the Inertia page object rendered for the request and renders it in the Inertia panel.
 *
 * Records the server-side page payload (component, props, URL, asset version) for both full page loads and Inertia
 * XHR visits, together with the `X-Inertia-*` negotiation headers, so partial reloads and version conflicts can be
 * inspected per capture. The panel enables itself only when the application wires the `yii2-extensions/inertia`
 * `Manager` under the `inertia` component id.
 *
 * @extends Panel<array{
 *   location: string|null,
 *   page: array<string, mixed>|null,
 *   requestHeaders: array<string, string>,
 *   sharedKeys: list<string>,
 *   statusCode: int,
 * }>
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
    /**
     * Page FQCN from `yii2-extensions/inertia`, referenced as a string to avoid a hard package dependency.
     */
    private const string PAGE_CLASS = 'yii\inertia\Page';
    /**
     * `X-Inertia-*` request headers captured for the panel, in display order.
     */
    private const array REQUEST_HEADERS = [
        'X-Inertia',
        'X-Inertia-Partial-Component',
        'X-Inertia-Partial-Data',
        'X-Inertia-Partial-Except',
        'X-Inertia-Reset',
        'X-Inertia-Version',
    ];

    /**
     * Page object captured from the root-view render params on full page loads, when present.
     */
    private JsonSerializable|null $page = null;

    /**
     * Renders the detail view from the captured page payload.
     */
    #[Override]
    public function getDetail(): string
    {
        return Yii::$app->view->render('panels/inertia/detail', ['panel' => $this], $this);
    }

    /**
     * Returns whether the loaded capture carries Inertia activity (a rendered page or an `X-Inertia` XHR).
     *
     * Keeps the sidebar entry per-capture: plain requests (assets, JSON endpoints, redirects) do not list the panel.
     */
    #[Override]
    public function hasContent(): bool
    {
        $data = is_array($this->data) ? $this->data : [];

        $requestHeaders = $data['requestHeaders'] ?? null;

        return is_array($data['page'] ?? null)
            || (is_array($requestHeaders) && isset($requestHeaders['X-Inertia']));
    }

    /**
     * Registers the render listener that captures the Inertia page from the root-view render params.
     *
     * Inertia XHR visits expose the page on `Response::$data`, but full page loads only pass it to the root view —
     * this listener records that object so {@see save()} covers both paths. The handler binds to the application
     * view instance rather than the `View` class, so it is released with the application instead of accumulating in
     * the class-level event registry across requests of a long-running worker.
     */
    public function init(): void
    {
        parent::init();

        Yii::$app->getView()->on(
            View::EVENT_BEFORE_RENDER,
            function (ViewEvent $event): void {
                $page = $event->params['page'] ?? null;

                if ($page instanceof JsonSerializable && is_a($page, self::PAGE_CLASS)) {
                    $this->page = $page;
                }
            },
        );
    }

    /**
     * Returns whether the application wires the Inertia manager under the `inertia` component id.
     */
    #[Override]
    public function isEnabled(): bool
    {
        if (Yii::$app->has(self::COMPONENT_ID) === false) {
            return false;
        }

        try {
            $component = Yii::$app->get(self::COMPONENT_ID);
        } catch (InvalidConfigException) {
            return false;
        }

        return is_object($component) && is_a($component, self::MANAGER_CLASS);
    }

    /**
     * Captures the page payload, the `X-Inertia-*` request headers, the shared-prop keys, and the response status.
     *
     * @return array{
     *   location: string|null,
     *   page: array<string, mixed>|null,
     *   requestHeaders: array<string, string>,
     *   sharedKeys: list<string>,
     *   statusCode: int,
     * } Captured Inertia snapshot; `page` is `null` when the response was not rendered through Inertia.
     */
    public function save(): array
    {
        $response = Yii::$app->getResponse();

        $page = $this->page;

        if ($page === null) {
            $data = $response->data;

            if ($data instanceof JsonSerializable && is_a($data, self::PAGE_CLASS)) {
                $page = $data;
            }
        }

        $location = $response->getHeaders()->get('X-Inertia-Location');

        return [
            'location' => is_string($location) ? $location : null,
            'page' => self::normalizePage($page),
            'requestHeaders' => self::requestHeaders(),
            'sharedKeys' => self::sharedKeys(),
            'statusCode' => $response->getStatusCode(),
        ];
    }

    /**
     * Returns the toolbar chip carrying the rendered component name, or `[]` when no page was captured.
     */
    #[Override]
    protected function getToolbarItems(): array
    {
        $data = is_array($this->data) ? $this->data : [];

        $page = $data['page'] ?? null;

        $component = is_array($page) && is_string($page['component'] ?? null) ? $page['component'] : '';

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

    /**
     * Round-trips the page object through JSON to produce a storable scalar/array payload.
     *
     * @return array<string, mixed>|null Normalized page payload, or `null` when the page is absent or not encodable.
     */
    private static function normalizePage(JsonSerializable|null $page): array|null
    {
        if ($page === null) {
            return null;
        }

        $encoded = json_encode($page);

        if (!is_string($encoded)) {
            return null;
        }

        $decoded = json_decode($encoded, true);

        if (!is_array($decoded)) {
            return null;
        }

        $normalized = [];

        foreach ($decoded as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }

    /**
     * Collects the non-empty `X-Inertia-*` request headers in display order.
     *
     * @return array<string, string> Header values indexed by header name.
     */
    private static function requestHeaders(): array
    {
        $headers = Yii::$app->getRequest()->getHeaders();

        $captured = [];

        foreach (self::REQUEST_HEADERS as $name) {
            $value = $headers->get($name);

            if (is_string($value) && $value !== '') {
                $captured[$name] = $value;
            }
        }

        return $captured;
    }

    /**
     * Returns the top-level shared-prop keys the manager exposes through its public `getShared()` accessor.
     *
     * The call goes through reflection because the manager is typed as `object` here — the panel refers to its class
     * by name to stay dependency-free, so static analysis cannot resolve the method. Managers without the accessor
     * raise a {@see ReflectionException} and simply yield no keys, leaving every prop labelled as page-specific.
     *
     * @return list<string> Shared keys, or `[]` when the manager is absent or exposes no shared props.
     */
    private static function sharedKeys(): array
    {
        try {
            $component = Yii::$app->get(self::COMPONENT_ID);
        } catch (InvalidConfigException) {
            return [];
        }

        if (!is_object($component) || !is_a($component, self::MANAGER_CLASS)) {
            return [];
        }

        try {
            $shared = (new ReflectionMethod($component, 'getShared'))->invoke($component);
        } catch (ReflectionException) {
            return [];
        }

        if (!is_array($shared)) {
            return [];
        }

        $keys = [];

        foreach (array_keys($shared) as $key) {
            $keys[] = (string) $key;
        }

        return $keys;
    }
}
