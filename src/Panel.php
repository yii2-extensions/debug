<?php

declare(strict_types=1);

namespace yii\debug;

use yii\base\Component;
use yii\helpers\ArrayHelper;
use yii\helpers\StringHelper;
use yii\helpers\Url;
use yii\helpers\VarDumper;
use function is_array;

/**
 * Panel is a base class for debugger panel classes. It defines how data should be collected, what should be displayed
 * at debug toolbar and on debugger details view.
 *
 * @property-read string $detail Content that is displayed in debugger detail view.
 * @property FlattenException|null $error Note that the type of this property differs in getter and setter.
 * See [[getError()]] and [[setError()]] for details.
 * @property-read string $name Name of the panel.
 * @property-read string $summary Content that is displayed at debug toolbar.
 * @property-read array<string, mixed> $toolbarData Structured data that is displayed at debug toolbar.
 * @property-read string $url URL pointing to panel detail view.
 */
class Panel extends Component
{
    /**
     * @var array array of actions to add to the debug modules default controller.
     * This array will be merged with all other panels actions property.
     * See [[\yii\base\Controller::actions()]] for the format.
     */
    public $actions = [];
    /**
     * @var mixed data associated with panel
     */
    public $data;
    /**
     * @var string panel unique identifier.
     * It is set automatically by the container module.
     */
    public $id;
    /**
     * @var Module
     */
    public $module;
    /**
     * @var string request data set identifier.
     */
    public $tag;

    /**
     * @var FlattenException|null Error while saving the panel
     * @since 2.0.10
     */
    protected $error;

    /**
     * @return string content that is displayed in debugger detail view
     */
    public function getDetail()
    {
        return '';
    }

    /**
     * @return FlattenException|null
     * @since 2.0.10
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @return string name of the panel
     */
    public function getName()
    {
        return '';
    }

    /**
     * @return string content that is displayed at debug toolbar
     */
    public function getSummary()
    {
        return '';
    }

    /**
     * @return array<string, mixed> structured data that is displayed at debug toolbar
     * @since 2.1.29
     */
    public function getToolbarData()
    {
        if ($this->hasError()) {
            $error = $this->getError();

            return [
                'title' => $this->getName(),
                'url' => $this->getUrl(),
                'items' => [
                    [
                        'label' => $this->getName(),
                        'value' => 'error',
                        'status' => 'danger',
                        'title' => $error === null ? 'Panel error' : $error->getMessage(),
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
     * The key is matched against an SVG file shipped at `src/assets/svg/{key}.svg` and rendered as
     * a CSS-mask glyph that takes its color from the surrounding chip text.
     *
     * @return string|null
     * @since 2.1.30
     */
    public function getToolbarIcon()
    {
        return null;
    }

    /**
     * Returns a trace line
     * @param array $options The array with trace
     * @return string the trace line
     * @since 2.0.7
     */
    public function getTraceLine($options)
    {
        /**
         * If an internal PHP function, such as `call_user_func`, in the backtrace, the 'file' and 'line' not be available.
         * @see https://www.php.net/manual/en/function.debug-backtrace.php#59713
         */
        if (!isset($options['file'])) {
            return VarDumper::dumpAsString($options);
        }

        if (!isset($options['text'])) {
            $options['text'] = "{$options['file']}:{$options['line']}";
        }
        $traceLine = $this->module->traceLine;
        if ($traceLine === false) {
            return $options['text'];
        }

        $options['file'] = str_replace('\\', '/', $options['file']);

        foreach ($this->module->tracePathMappings as $old => $new) {
            $old = rtrim(str_replace('\\', '/', $old), '/') . '/';

            if (StringHelper::startsWith($options['file'], $old)) {
                $new = rtrim(str_replace('\\', '/', $new), '/') . '/';
                $options['file'] = $new . substr($options['file'], strlen($old));
                break;
            }
        }

        $rawLink = $traceLine instanceof \Closure ? $traceLine($options, $this) : $traceLine;

        return strtr($rawLink, ['{file}' => $options['file'], '{line}' => $options['line'], '{text}' => $options['text']]);
    }

    /**
     * @param array|null $additionalParams Optional additional parameters to add to the route
     * @return string URL pointing to panel detail view
     */
    public function getUrl($additionalParams = null)
    {
        $route = [
            '/' . $this->module->getUniqueId() . '/default/view',
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
     * @return bool
     */
    public function hasRequestNavigation()
    {
        return true;
    }

    /**
     * Checks whether this panel is enabled.
     *
     * @return bool whether this panel is enabled.
     */
    public function isEnabled()
    {
        return true;
    }

    /**
     * Loads data into the panel
     *
     * @param mixed $data
     */
    public function load($data)
    {
        $this->data = $data;
    }

    /**
     * Saves data to be later used in debugger detail view.
     * This method is called on every page where debugger is enabled.
     *
     * @return mixed data to be saved
     */
    public function save()
    {
        return null;
    }

    public function setError(FlattenException $error)
    {
        $this->error = $error;
    }

    /**
     * Gets messages from log target and filters according to their categories and levels.
     *
     * @param int $levels the message levels to filter by. This is a bitmap of level values. Value 0 means allowing all
     * levels.
     * @param array $categories the message categories to filter by. If empty, it means all categories are allowed.
     * @param array $except the message categories to exclude. If empty, it means all categories are allowed.
     * @param bool $stringify Convert non-string (such as closures) to strings
     *
     * @return array the filtered messages.
     *
     * @see \yii\log\Target::filterMessages()
     */
    protected function getLogMessages($levels = 0, $categories = [], $except = [], $stringify = false)
    {
        $target = $this->module->logTarget;

        $messages = $target->filterMessages($target->messages, $levels, $categories, $except);

        if (!$stringify) {
            return $messages;
        }

        foreach ($messages as &$message) {
            if (!isset($message[0]) || is_string($message[0])) {
                continue;
            }

            // exceptions may not be serializable if in the call stack somewhere is a Closure
            if ($message[0] instanceof \Throwable || $message[0] instanceof \Exception) {
                $message[0] = (string) $message[0];
            } else {
                $message[0] = VarDumper::export($message[0]);
            }
        }

        return $messages;
    }

    /**
     * Returns the structured items to be rendered on the debug toolbar for this panel.
     *
     * Subclasses override this instead of [[getToolbarData()]]. The base implementation in [[getToolbarData()]] handles
     * the error envelope, the title/url/items wrapping, and the legacy HTML summary fallback.
     *
     * Return value semantics:
     * - a non-empty array of item descriptors: rendered as structured metrics on the toolbar,
     * - an empty array (`[]`, the default): falls back to the legacy [[getSummary()]] HTML,
     * - `null`: the panel is skipped entirely on the toolbar.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function getToolbarItems()
    {
        return [];
    }
}
