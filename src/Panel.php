<?php

declare(strict_types=1);

namespace yii\debug;

use Throwable;
use yii\base\{Component, InvalidConfigException};
use yii\debug\helpers\Coerce;
use yii\helpers\{ArrayHelper, StringHelper, Url, VarDumper};

use function array_key_exists;
use function is_array;
use function is_string;
use function strlen;

/**
 * Base class for debug toolbar panels.
 *
 * Defines the contract every panel implements: how request data is captured on `save()`, rehydrated on `load()`, and
 * surfaced on the toolbar and detail views. The container {@see Module} wires {@see $id}, {@see $module}, and
 * {@see $tag} automatically on registration.
 */
class Panel extends Component
{
    /**
     * @var array<array-key, array{class: class-string, ...}|class-string> Extra actions merged into the debug module's
     * default controller. See {@see \yii\base\Controller::actions()} for the accepted shape.
     */
    public array $actions = [];
    /**
     * Captured panel payload as produced by {@see save()} and rehydrated by {@see load()}.
     */
    public mixed $data = null;
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
     * Exception captured during {@see save()}, when the panel failed to produce its payload.
     */
    protected FlattenException|null $error = null;

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
    public function getError(): FlattenException|null
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
        return '';
    }

    /**
     * Returns the legacy HTML summary rendered on the toolbar when {@see getToolbarItems()} yields `[]`.
     *
     * @return string Summary markup; `''` when the panel does not contribute a summary.
     */
    public function getSummary(): string
    {
        return '';
    }

    /**
     * Returns the toolbar envelope wrapping the panel's icon, items, and URL.
     *
     * Renders the error envelope when {@see getError()} is non-`null`, the structured-items path when
     * {@see getToolbarItems()} returns a non-empty list, and the legacy HTML summary fallback otherwise.
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

        if ($items === null) {
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

        if ($items !== []) {
            $envelope['items'] = $items;

            return $envelope;
        }

        $summary = $this->getSummary();

        if ($summary === '') {
            return [];
        }

        $envelope['html'] = $summary;

        return $envelope;
    }

    /**
     * Returns the icon key used on the panel's toolbar chip, or `null` to render no icon.
     *
     * The key is matched against an SVG file shipped at `src/assets/svg/{key}.svg` and rendered as a CSS-mask glyph
     * that takes its color from the surrounding chip text.
     *
     * @return string|null Icon key, or `null` to render no icon.
     */
    public function getToolbarIcon(): string|null
    {
        return null;
    }

    /**
     * Builds a trace line for the toolbar, applying {@see Module::$tracePathMappings} and the configured
     * {@see Module::$traceLine} template (or callable).
     *
     * Falls back to dumping the input when `file` or `line` is missing — internal PHP functions such as
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

        $rawLink = $traceLine instanceof \Closure ? $traceLine($options, $this) : $traceLine;
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
        $route = [
            '/' . $this->module?->getUniqueId() . '/default/view',
            'panel' => $this->id,
            'tag' => $this->tag,
        ];

        if ($additionalParams !== null) {
            $route = ArrayHelper::merge($route, $additionalParams);
        }

        return Url::toRoute($route);
    }

    /**
     * Returns `true` when {@see setError()} captured a {@see FlattenException} during {@see save()}.
     */
    public function hasError(): bool
    {
        return $this->error !== null;
    }

    /**
     * Indicates whether the detail view exposes the Prev/Next/All/Latest/Last-10 navigation across captured requests.
     *
     * Returns `true` by default. Override to `false` on panels whose data is request-agnostic (for example,
     * configuration snapshots), where stepping between request tags does not change what the user sees.
     */
    public function hasRequestNavigation(): bool
    {
        return true;
    }

    /**
     * Indicates whether this panel is enabled and should be registered by the module.
     */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * Hydrates the panel from the payload previously produced by {@see save()}.
     *
     * Invoked by {@see LogTarget::loadTagToPanels()} when the user opens a captured request.
     *
     * @param mixed $data Payload returned by {@see save()}; format is panel-specific.
     */
    public function load(mixed $data): void
    {
        $this->data = $data;
    }

    /**
     * Captures the panel payload for the current request.
     *
     * Invoked by {@see LogTarget::export()} at request end; the return value is serialized into the `<tag>.data` file
     * and rehydrated by {@see load()} on read-back.
     *
     * @return mixed Payload to persist; `null` when the panel records nothing.
     */
    public function save(): mixed
    {
        return null;
    }

    /**
     * Records an exception thrown by {@see save()} so {@see LogTarget} can surface it on the toolbar and detail view.
     */
    public function setError(FlattenException $error): void
    {
        $this->error = $error;
    }

    /**
     * Returns the log messages captured by the debug log target, filtered by levels and categories.
     *
     * When `$stringify` is `true`, non-string first elements are exported via {@see VarDumper::export()}, with
     * {@see Throwable} instances cast to their string form — closures captured in exception traces are not directly
     * serializable, so the cast guards the manifest from breaking on read-back.
     *
     * @param int $levels Bitmap of {@see \yii\log\Logger} level constants; `0` allows every level.
     * @param array<int, string> $categories Allowed category names; `[]` allows every category.
     * @param array<int, string> $except Category names to exclude; `[]` excludes none.
     * @param bool $stringify `true` to convert non-string first elements (closures, exceptions) into strings.
     *
     * @throws InvalidConfigException When the debug log target is not initialized.
     *
     * @return array<int, array<int|string, mixed>> Filtered messages in capture order.
     *
     * @see \yii\log\Target::filterMessages()
     */
    protected function getLogMessages(
        int $levels = 0,
        array $categories = [],
        array $except = [],
        bool $stringify = false,
    ): array {
        $target = $this->getLogTarget();

        $filteredMessages = LogTarget::filterMessages($target->messages, $levels, $categories, $except);

        $messages = [];

        foreach ($filteredMessages as $message) {
            if (is_array($message)) {
                $messages[] = $message;
            }
        }

        if (!$stringify) {
            return $messages;
        }

        foreach ($messages as $key => $message) {
            if (!array_key_exists(0, $message) || is_string($message[0])) {
                continue;
            }

            if ($message[0] instanceof Throwable) {
                $messages[$key][0] = (string) $message[0];
            } else {
                $messages[$key][0] = VarDumper::export($message[0]);
            }
        }

        return $messages;
    }

    /**
     * Returns the debug log target wired to the owning module.
     *
     * @throws InvalidConfigException When the debug module has not initialized its log target.
     */
    protected function getLogTarget(): LogTarget
    {
        $logTarget = $this->module?->logTarget;

        if (!$logTarget instanceof LogTarget) {
            throw new InvalidConfigException(
                'The debug module logTarget must be initialized before reading log messages.',
            );
        }

        return $logTarget;
    }

    /**
     * Returns the structured items rendered on the debug toolbar for this panel.
     *
     * Subclasses override this instead of {@see getToolbarData()}, which handles the error envelope, the
     * title/url/items wrapping, and the legacy HTML summary fallback.
     *
     * Return value semantics:
     * - a non-empty list of item descriptors: rendered as structured metrics on the toolbar,
     * - `[]` (the default): falls back to the legacy {@see getSummary()} HTML,
     * - `null`: the panel is skipped entirely on the toolbar.
     *
     * @return array<int, array<string, mixed>>|null Structured items, or `null` to skip the panel.
     */
    protected function getToolbarItems(): array|null
    {
        return [];
    }
}
