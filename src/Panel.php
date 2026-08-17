<?php

declare(strict_types=1);

namespace yii\debug;

use Closure;
use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Storage\{ExceptionSnapshot, HydrationException, PanelSnapshot};
use yii\base\{Component, ViewContextInterface};
use yii\helpers\{ArrayHelper, StringHelper, Url, VarDumper};

use function strlen;

/**
 * Base class for debug toolbar panels.
 *
 * Defines the contract every panel implements: how request data is captured, hydrated, and
 * surfaced on the toolbar and detail views. The container {@see Module} wires {@see $id}, {@see $module}, and
 * {@see $tag} automatically on registration.
 */
class Panel extends Component implements ViewContextInterface
{
    /**
     * SVG icon key used by the toolbar.
     */
    protected const string|null ICON = null;
    /**
     * Panel display name.
     */
    protected const string NAME = '';

    /**
     * @var array<array-key, array{class: class-string, ...}|class-string> Extra actions merged into the debug module's
     * default controller. See {@see \yii\base\Controller::actions()} for the accepted shape.
     */
    public array $actions = [];
    /**
     * Panel unique identifier, assigned by the container module on registration.
     */
    public string $id = '';
    /**
     * Debug module owning this panel.
     */
    public Module|null $module = null;
    /**
     * Tag of the request whose data this panel currently exposes.
     */
    public string $tag = '';

    /**
     * Exception captured when the panel failed to produce or hydrate its snapshot.
     */
    protected ExceptionSnapshot|null $error = null;

    /**
     * Captures the panel payload for the current request.
     *
     * Invoked by {@see LogTarget::export()} at request end; the DTO is encoded into the request's versioned JSON
     * snapshot and rehydrated through {@see hydrate()} on read-back.
     *
     * @return PanelSnapshot|null Typed payload to persist; `null` when the panel records nothing.
     */
    public function capture(): PanelSnapshot|null
    {
        return null;
    }

    /**
     * Returns the detail view markup rendered when the user opens the panel.
     *
     * @return string Detail view markup; `''` when the panel does not expose a detail view.
     */
    public function getDetail(): string
    {
        return '';
    }

    /**
     * Returns the exception captured while collecting the panel payload, if any.
     */
    public function getError(): ExceptionSnapshot|null
    {
        return $this->error;
    }

    /**
     * Returns the panel display name shown on the toolbar and the detail navigation.
     *
     * @return string Display name; `''` for the base class.
     */
    public function getName(): string
    {
        return static::NAME;
    }

    /**
     * Returns the toolbar envelope wrapping the panel's icon, items, and URL.
     *
     * Renders the error envelope when {@see getError()} is non-`null`; otherwise wraps the structured items from
     * {@see getToolbarItems()}. Panels that yield no items are skipped.
     *
     * @return array<string, mixed> Toolbar envelope; `[]` to skip the panel.
     */
    public function getToolbarData(): array
    {
        $error = $this->getError();

        if ($error !== null) {
            return [
                'title' => $this->getName(),
                'url' => $this->getUrl(),
                'items' => [
                    [
                        'label' => $this->getName(),
                        'status' => 'danger',
                        'title' => $error->getMessage(),
                        'value' => 'error',
                    ],
                ],
            ];
        }

        $items = $this->getToolbarItems();

        if ($items === []) {
            return [];
        }

        $envelope = [
            'title' => $this->getName(),
            'url' => $this->getUrl(),
        ];

        $icon = $this->getToolbarIcon();

        if ($icon !== null && $icon !== '') {
            $envelope['icon'] = $icon;
        }

        $envelope['items'] = $items;

        return $envelope;
    }

    /**
     * Returns the icon key used on the panel's toolbar chip, or `null` to render no icon.
     *
     * The key is matched against the SVG library shipped by `php-forge/debug-core` and rendered as a CSS-mask glyph
     * that takes its color from the surrounding chip text.
     *
     * @return string|null Icon key, or `null` to render no icon.
     */
    public function getToolbarIcon(): string|null
    {
        return static::ICON;
    }

    /**
     * Builds a trace line for the toolbar, applying {@see Module::$tracePathMappings} and the configured
     * {@see Module::$traceLine} template (or callable).
     *
     * Falls back to dumping the input when `file` or `line` is missing internal PHP functions such as
     * {@see call_user_func()} may produce frames without those keys, see
     * {@link https://www.php.net/manual/en/function.debug-backtrace.php#59713}.
     *
     * @param array<string, mixed> $options Trace frame; consumes `file`, `line`, and optional `text`.
     *
     * @return string Trace line ready for inclusion on the toolbar.
     */
    public function getTraceLine(array $options): string
    {
        $file = Coerce::stringOrNull($options['file'] ?? null);
        $line = Coerce::stringOrNull($options['line'] ?? null);

        if ($file === null || $line === null) {
            return VarDumper::dumpAsString($options);
        }

        if (!isset($options['text'])) {
            $text = "{$file}:{$line}";
        } else {
            $text = Coerce::stringOrNull($options['text']) ?? VarDumper::dumpAsString($options['text']);
        }

        $traceLine = $this->module?->traceLine;

        if ($traceLine === null || $traceLine === false) {
            return $text;
        }

        $file = str_replace('\\', '/', $file);

        foreach ($this->module->tracePathMappings as $old => $new) {
            $old = Coerce::stringOrNull($old);
            $new = Coerce::stringOrNull($new);

            if ($old === null || $new === null) {
                continue;
            }

            $old = rtrim(str_replace('\\', '/', $old), '/') . '/';

            if (StringHelper::startsWith($file, $old)) {
                $new = rtrim(str_replace('\\', '/', $new), '/') . '/';
                $file = $new . substr($file, strlen($old));

                break;
            }
        }

        $options['file'] = $file;
        $options['line'] = $line;
        $options['text'] = $text;

        $rawLink = $traceLine instanceof Closure ? $traceLine($options, $this) : $traceLine;
        $rawLinkString = Coerce::stringOrNull($rawLink);

        if ($rawLinkString === null) {
            return VarDumper::dumpAsString($rawLink);
        }

        return strtr($rawLinkString, ['{file}' => $file, '{line}' => $line, '{text}' => $text]);
    }

    /**
     * Returns the URL pointing to this panel's detail view for the current request tag.
     *
     * @param array<string, mixed>|null $additionalParams Extra query parameters merged into the route.
     *
     * @return string Absolute URL to the panel detail view.
     */
    public function getUrl(array|null $additionalParams = null): string
    {
        $moduleId = $this->module?->getUniqueId();

        $route = [
            "/{$moduleId}/default/view",
            'panel' => $this->id,
            'tag' => $this->tag,
        ];

        if ($additionalParams !== null) {
            $route = ArrayHelper::merge($route, $additionalParams);
        }

        return Url::toRoute($route);
    }

    /**
     * Returns the directory under which the panel's relative views resolve.
     */
    public function getViewPath(): string
    {
        return __DIR__ . '/views/default';
    }

    /**
     * Returns whether the panel captured content for the loaded request.
     *
     * The sidebar nav skips panels that report `false` for the active capture, so integration panels can activate
     * per request — the way the AJAX flag only surfaces on XHR captures. Returns `true` by default, keeping every
     * core panel listed on every capture.
     */
    public function hasContent(): bool
    {
        return true;
    }

    /**
     * Returns `true` when {@see setError()} captured an exception during panel capture or hydration.
     */
    public function hasError(): bool
    {
        return $this->error !== null;
    }

    /**
     * Hydrates the panel from the JSON payload previously produced by {@see capture()}.
     *
     * Invoked by {@see LogTarget::loadTagToPanels()} when the user opens a captured request.
     *
     * @param array<string, mixed> $payload Panel-specific JSON object.
     */
    public function hydrate(array $payload): void
    {
        throw HydrationException::at("$.panels.{$this->id}", 'a payload supported by this panel');
    }

    /**
     * Indicates whether this panel is enabled and should be registered by the module.
     */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * Records an exception thrown during capture or hydration so {@see LogTarget} can surface it in the UI.
     */
    public function setError(ExceptionSnapshot $error): void
    {
        $this->error = $error;
    }

    /**
     * Returns the structured items rendered on the debug toolbar for this panel.
     *
     * Subclasses override this instead of {@see getToolbarData()}, which handles the error envelope and the
     * title/url/items wrapping.
     *
     * Return value semantics:
     * - a non-empty list of item descriptors: rendered as structured metrics on the toolbar,
     * - `[]` (the default): the panel renders no toolbar chip.
     *
     * @return array<int, array<string, mixed>> Structured items; `[]` to skip the panel.
     */
    protected function getToolbarItems(): array
    {
        return [];
    }
}
