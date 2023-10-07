<?php

declare(strict_types=1);

namespace yii\debug;

use Closure;
use Throwable;
use yii\base\Component;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\helpers\VarDumper;
use yii\helpers\StringHelper;

/**
 * Panel is a base class for debugger panel classes.
 * It defines how data should be collected, what should be displayed
 * in the debug toolbar and on the debugger details view.
 */
class Panel extends Component
{
    /**
     * @var string panel unique identifier.
     * It is set automatically by the container module.
     */
    public string $id = '';
    /**
     * @var string request data set identifier.
     */
    public string $tag = '';
    public Module|null $module = null;
    /**
     * @var mixed data associated with panel.
     */
    public mixed $data;
    /**
     * @var array array of actions to add to the debug modules default controller.
     * This array will be merged with all other panels actions property.
     * See [[\yii\base\Controller::actions()]] for the format.
     */
    public array $actions = [];

    /**
     * @var FlattenException|null Error while saving the panel.
     */
    protected FlattenException|null $error = null;

    /**
     * @return string name of the panel.
     */
    public function getName(): string
    {
        return '';
    }

    /**
     * @return string content that is displayed the debug toolbar.
     */
    public function getSummary(): string
    {
        return '';
    }

    /**
     * @return string content that is displayed in debugger detail view.
     */
    public function getDetail(): string
    {
        return '';
    }

    /**
     * Saves data to be later used in the debugger detail view.
     * This method is called on every page where the debugger is enabled.
     *
     * @return mixed data to be saved
     */
    public function save(): mixed
    {
        return null;
    }

    /**
     * Loads data into the panel
     *
     * @param mixed $data
     */
    public function load(mixed $data): void
    {
        $this->data = $data;
    }

    /**
     * @param array|null $additionalParams Optional additional parameters to add to the route.
     *
     * @return string URL pointing to panel detail view.
     */
    public function getUrl(array $additionalParams = null): string
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
     * Returns a trace line.
     *
     * @param array $options The array with trace.
     *
     * @return string the trace line.
     */
    public function getTraceLine(array $options): string
    {
        if ($this->module === null) {
            return '';
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

        $rawLink = $traceLine instanceof Closure ? $traceLine($options, $this) : $traceLine;

        return strtr($rawLink, ['{file}' => $options['file'], '{line}' => $options['line'], '{text}' => $options['text']]);
    }

    /**
     * @param FlattenException $error
     */
    public function setError(FlattenException $error): void
    {
        $this->error = $error;
    }

    /**
     * @return FlattenException|null
     */
    public function getError(): ?FlattenException
    {
        return $this->error;
    }

    public function hasError(): bool
    {
        return $this->error !== null;
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
     * Gets messages from log target and filters according to their categories and levels.
     *
     * @param int $levels the message levels to filter by. This is a bitmap of level values. Value 0 means allowing
     * all levels.
     * @param array $categories the message categories to filter by. If empty, it means all categories are allowed.
     * @param array $except the message categories to exclude. If empty, it means all categories are allowed.
     * @param bool $stringify Convert non-string (such as closures) to strings.
     *
     * @return array the filtered messages.
     *
     * @see \yii\log\Target::filterMessages()
     */
    protected function getLogMessages(
        int $levels = 0,
        array $categories = [],
        array $except = [],
        bool $stringify = false
    ): array {
        if ($this->module === null) {
            return [];
        }

        $target = $this->module->logTarget;
        $messages = $target->filterMessages(...);

        if (!$stringify) {
            return $messages;
        }

        foreach ($messages as &$message) {
            if (!isset($message[0]) || is_string($message[0])) {
                continue;
            }

            // exceptions may not be serializable if in the call stack somewhere is a Closure.
            if ($message[0] instanceof Throwable) {
                $message[0] = (string) $message[0];
            } else {
                $message[0] = VarDumper::export($message[0]);
            }
        }

        return $messages;
    }
}
