<?php

declare(strict_types=1);

namespace yii\debug;

use Stringable;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\{ArrayHelper, StringHelper, Url, VarDumper};

use function array_key_exists;
use function is_array;
use function is_scalar;
use function is_string;
use function strlen;

/**
 * Panel is a base class for debugger panel classes. It defines how data should be collected, what should be displayed
 * at debug toolbar and on debugger details view.
 */
class Panel extends Component
{
    /**
     * @var array<array-key, array{class: class-string, ...}|class-string> Array of actions to add to the debug modules
     * default controller.
     *
     * This array will be merged with all other panels actions property.
     * {@see \yii\base\Controller::actions()} for the format.
     */
    public array $actions = [];
    /**
     * Data associated with panel
     */
    public mixed $data = null;
    /**
     * Panel unique identifier, it is set automatically by the container module.
     */
    public string $id = '';
    /**
     * Module that this panel belongs to. It is set automatically by the container module.
     */
    public Module|null $module = null;
    /**
     * Request data set identifier.
     */
    public string $tag = '';

    /**
     * Error while saving the panel.
     */
    protected FlattenException|null $error = null;

    /**
     * @return string content that is displayed in debugger detail view
     */
    public function getDetail()
    {
        return '';
    }

    /**
     * @return FlattenException|null
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @return string Name of the panel.
     */
    public function getName()
    {
        return '';
    }

    /**
     * @return string Content that is displayed at debug toolbar.
     */
    public function getSummary()
    {
        return '';
    }

    /**
     * @return array<string, mixed> Structured data that is displayed at debug toolbar.
     */
    public function getToolbarData(): array
    {
        if ($this->hasError()) {
            $error = $this->getError();

            return [
                'title' => $this->getName(),
                'url' => $this->getUrl(),
                'items' => [
                    [
                        'label' => $this->getName(),
                        'status' => 'danger',
                        'title' => $error === null ? 'Panel error' : $error->getMessage(),
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
     * Returns a trace line
     *
     * @param array<string, mixed> $options Array with trace.
     *
     * @return string Trace line to be displayed in the toolbar. If the 'text' key is not set, it will be generated as
     * "file:line". If the 'file' or 'line' keys are not set, the whole $options array will be dumped as a string.
     */
    public function getTraceLine(array $options): string
    {
        /**
         * If an internal PHP function, such as `call_user_func`, is in the backtrace, the 'file' and 'line' may not
         * be available.
         * @see https://www.php.net/manual/en/function.debug-backtrace.php#59713
         */
        $file = $this->stringValue($options['file'] ?? null);
        $line = $this->stringValue($options['line'] ?? null);

        if ($file === null || $line === null) {
            return VarDumper::dumpAsString($options);
        }

        if (!isset($options['text'])) {
            $text = "{$file}:{$line}";
        } else {
            $text = $this->stringValue($options['text']) ?? VarDumper::dumpAsString($options['text']);
        }

        $traceLine = $this->module?->traceLine;

        if ($traceLine === null || $traceLine === false) {
            return $text;
        }

        $file = str_replace('\\', '/', $file);

        foreach ($this->module->tracePathMappings as $old => $new) {
            $old = $this->stringValue($old);
            $new = $this->stringValue($new);

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
        $rawLinkString = $this->stringValue($rawLink);

        if ($rawLinkString === null) {
            return VarDumper::dumpAsString($rawLink);
        }

        return strtr($rawLinkString, ['{file}' => $file, '{line}' => $line, '{text}' => $text]);
    }

    /**
     * @param array<string, mixed>|null $additionalParams Optional additional parameters to add to the route.
     *
     * @return string URL pointing to panel detail view.
     */
    public function getUrl($additionalParams = null): string
    {
        $route = [
            '/' . $this->module?->getUniqueId() . '/default/view',
            'panel' => $this->id,
            'tag' => $this->tag,
        ];

        if (is_array($additionalParams)) {
            $route = ArrayHelper::merge($route, $additionalParams);
        }

        return Url::toRoute($route);
    }

    /**
     * @return bool
     */
    public function hasError()
    {
        return $this->error !== null;
    }

    /**
     * Whether the detail page for this panel should show the Prev/Next/All/Latest/Last-10 navigation between captured
     * requests.
     *
     * Returns `true` by default. Override to `false` on panels whose data is request-agnostic (for example,
     * configuration snapshots), where stepping between request tags does not change what the user sees.
     *
     * @return bool whether the detail page for this panel should show the Prev/Next/All/Latest/Last-10 navigation
     * between captured requests.
     */
    public function hasRequestNavigation(): bool
    {
        return true;
    }

    /**
     * Checks whether this panel is enabled.
     *
     * @return bool whether this panel is enabled.
     */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * Loads data into the panel to be later used in debugger detail view. This method is called on every page where
     * debugger is enabled.
     *
     * @param mixed $data Data to be loaded into the panel. The content and format of this data is determined by the
     * caller, but it is
     */
    public function load(mixed $data): void
    {
        $this->data = $data;
    }

    /**
     * Saves data to be later used in debugger detail view.
     *
     * This method is called on every page where debugger is enabled.
     *
     * @return mixed Data to be saved
     */
    public function save(): mixed
    {
        return null;
    }

    public function setError(FlattenException $error): void
    {
        $this->error = $error;
    }

    /**
     * Gets messages from log target and filters according to their categories and levels.
     *
     * @param int $levels the message levels to filter by. This is a bitmap of level values. Value 0 means allowing all
     * levels.
     * @param array<int, string> $categories the message categories to filter by. If empty, all categories are allowed.
     * @param array<int, string> $except the message categories to exclude. If empty, all categories are allowed.
     * @param bool $stringify Convert non-string (such as closures) to strings
     *
     * @throws InvalidConfigException if the debug log target is not initialized.
     *
     * @return array<int, array<int|string, mixed>> the filtered messages.
     *
     * @see \yii\log\Target::filterMessages()
     */
    protected function getLogMessages($levels = 0, $categories = [], $except = [], $stringify = false): array
    {
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

            // exceptions may not be serializable if in the call stack somewhere is a Closure
            if ($message[0] instanceof \Throwable) {
                $messages[$key][0] = (string) $message[0];
            } else {
                $messages[$key][0] = VarDumper::export($message[0]);
            }
        }

        return $messages;
    }

    /**
     * Returns the debug log target instance.
     *
     * @throws InvalidConfigException if the debug log target is not initialized.
     *
     * @return LogTarget Debug log target instance.
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
     * Returns the structured items to be rendered on the debug toolbar for this panel.
     *
     * Subclasses override this instead of [[getToolbarData()]]. The base implementation in {@see getToolbarData()}
     * handles the error envelope, the title/url/items wrapping, and the legacy HTML summary fallback.
     *
     * Return value semantics:
     * - a non-empty array of item descriptors: rendered as structured metrics on the toolbar,
     * - an empty array (`[]`, the default): falls back to the legacy [[getSummary()]] HTML,
     * - `null`: the panel is skipped entirely on the toolbar.
     *
     * @return array<int, array<string, mixed>>|null Structured items to be rendered on the debug toolbar for this
     * panel, or `null` to skip the panel entirely on the toolbar.
     */
    protected function getToolbarItems()
    {
        return [];
    }

    /**
     * Converts a value to string if it is scalar or Stringable, otherwise returns null.
     *
     * @param mixed $value Value to convert.
     *
     * @return string|null String representation of the value, or `null` if it cannot be converted to string.
     */
    private function stringValue($value): string|null
    {
        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return null;
    }
}
