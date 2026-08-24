<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use Closure;
use JsonSerializable;
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Panel\Inertia\InertiaSnapshot;
use ReflectionMethod;
use Yii;
use yii\base\{InvalidConfigException, View, ViewEvent};

use function array_keys;
use function is_a;
use function is_array;
use function is_object;
use function is_string;
use function json_decode;
use function json_encode;

/**
 * Captures the Inertia page object rendered for the request for the Inertia panel.
 *
 * Records the server-side page payload (component, props, URL, asset version) for both full page loads and Inertia
 * XHR visits, together with the `X-Inertia-*` negotiation headers. The collector captures only when the application
 * wires the `yii2-extensions/inertia` `Manager` under the `inertia` component id.
 *
 * Usage example:
 *
 * ```php
 * $snapshot = (new \yii\debug\collectors\InertiaCollector())->capture();
 * ```
 */
class InertiaCollector extends Collector
{
    /**
     * Application component id under which the Inertia manager is registered.
     */
    private const string COMPONENT_ID = 'inertia';
    /**
     * Page FQCN returned by earlier adapter releases, retained while applications migrate to the portable core DTO.
     */
    private const string LEGACY_PAGE_CLASS = 'yii\inertia\Page';
    /**
     * Manager FQCN from `yii2-extensions/inertia`, referenced as a string to avoid a hard package dependency.
     */
    private const string MANAGER_CLASS = 'yii\inertia\Manager';
    /**
     * Framework-neutral page FQCN returned by current `yii2-extensions/inertia` releases.
     */
    private const string PAGE_CLASS = 'PHPForge\Inertia\Page';
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
    private CapturePolicy|null $capturePolicy = null;

    /**
     * @var (Closure(ViewEvent): void)|null Active render listener, kept so {@see stop()} can detach it.
     */
    private Closure|null $listener = null;
    /**
     * Page object captured from the root-view render params on full page loads, when present.
     */
    private JsonSerializable|null $page = null;

    /**
     * Captures the page payload, the `X-Inertia-*` request headers, the shared-prop keys, and the response status.
     *
     * @return InertiaSnapshot|null Captured Inertia payload; `null` when the collector never started or the
     * application wires no Inertia manager.
     */
    public function capture(): InertiaSnapshot|null
    {
        if (!$this->isStarted() || !self::managerPresent()) {
            return null;
        }

        $response = Yii::$app->getResponse();

        $page = $this->page;

        if ($page === null) {
            $data = $response->data;

            if (self::isPage($data)) {
                $page = $data;
            }
        }

        $location = $response->getHeaders()->get('X-Inertia-Location');

        $policy = $this->capturePolicy();

        return InertiaSnapshot::capture(
            location: is_string($location) ? $policy->redactUrl($location) : null,
            page: $this->normalizePage($page),
            requestHeaders: self::requestHeaders(),
            sharedKeys: self::sharedKeys(),
            statusCode: $response->getStatusCode(),
        );
    }

    /**
     * Returns the stable ID pairing this collector with the Inertia panel.
     *
     * Usage example:
     *
     * ```php
     * $id = (new \yii\debug\collectors\InertiaCollector())->id();
     * ```
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'inertia';
    }

    /**
     * Registers the render listener that captures the Inertia page from the root-view render params.
     *
     * Inertia XHR visits expose the page on `Response::$data`, but full page loads only pass it to the root view —
     * this listener records that object so {@see capture()} covers both paths. The handler binds to the application
     * view instance rather than the `View` class, so it is released with the application instead of accumulating in
     * the class-level event registry across requests of a long-running worker.
     */
    protected function start(): void
    {
        $this->page = null;
        $this->listener = function (ViewEvent $event): void {
            $page = $event->params['page'] ?? null;

            if (self::isPage($page)) {
                $this->page = $page;
            }
        };

        Yii::$app->getView()->on(View::EVENT_BEFORE_RENDER, $this->listener);
    }

    /**
     * Detaches the render listener and clears the captured page, so a reused worker process starts clean.
     */
    protected function stop(): void
    {
        if ($this->listener !== null) {
            Yii::$app->getView()->off(View::EVENT_BEFORE_RENDER, $this->listener);

            $this->listener = null;
        }

        $this->page = null;
    }

    /**
     * Returns the shared module capture policy, falling back to Debug Core defaults for standalone collector use.
     */
    private function capturePolicy(): CapturePolicy
    {
        return $this->capturePolicy ??= $this->module?->createCapturePolicy() ?? new CapturePolicy();
    }

    /**
     * Returns whether the value is a serializable Inertia page from either the current core or the legacy adapter DTO.
     *
     * @phpstan-assert-if-true JsonSerializable $value
     */
    private static function isPage(mixed $value): bool
    {
        return $value instanceof JsonSerializable
            && (is_a($value, self::PAGE_CLASS) || is_a($value, self::LEGACY_PAGE_CLASS));
    }

    /**
     * Returns whether the application wires the Inertia manager under the `inertia` component id.
     */
    private static function managerPresent(): bool
    {
        try {
            $component = Yii::$app->get(self::COMPONENT_ID);
        } catch (InvalidConfigException) {
            return false;
        }

        return is_object($component) && is_a($component, self::MANAGER_CLASS);
    }

    /**
     * Round-trips the page object through JSON to produce a storable scalar/array payload.
     *
     * @return array<string, mixed>|null Normalized page payload, or `null` when the page is absent or not encodable.
     */
    private function normalizePage(JsonSerializable|null $page): array|null
    {
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

        $policy = $this->capturePolicy();
        $normalized = $policy->redact($normalized);
        $url = $normalized['url'] ?? null;

        if (is_string($url)) {
            $normalized['url'] = $policy->redactUrl($url);
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
     * The call goes through reflection because the manager is typed as `object` here — the collector refers to its
     * class by name to stay dependency-free, so static analysis cannot resolve the method.
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

        $shared = (new ReflectionMethod($component, 'getShared'))->invoke($component);

        $keys = [];

        foreach (array_keys(is_array($shared) ? $shared : []) as $key) {
            $keys[] = (string) $key;
        }

        return $keys;
    }
}
