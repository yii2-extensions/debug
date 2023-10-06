<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Exception;
use Yii;
use yii\base\InlineAction;
use yii\base\InvalidConfigException;
use yii\debug\Panel;
use yii\helpers\ArrayHelper;
use yii\web\Session;

/**
 * Debugger panel that collects and displays request data.
 */
class RequestPanel extends Panel
{
    /**
     * @var array list of the PHP predefined variables that are allowed to be displayed in the request panel.
     *
     * Note that a variable must be accessible via `$GLOBALS`. Otherwise, it won't be displayed.
     */
    public array $displayVars = ['_SERVER', '_GET', '_POST', '_COOKIE', '_FILES', '_SESSION'];
    /**
     * @var array list of variable names which values should be censored in the output
     */
    public array $censoredVariableNames = [];
    /**
     * @var string value to display instead of the variable value if the name is on the censor list
     *
     * @since 2.1.20
     */
    public string $censorString = '****';

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Request';
    }

    /**
     * {@inheritdoc}
     */
    public function getSummary(): string
    {
        return Yii::$app->view->render('panels/request/summary', ['panel' => $this]);
    }

    /**
     * {@inheritdoc}
     */
    public function getDetail(): string
    {
        return Yii::$app->view->render('panels/request/detail', ['panel' => $this]);
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function save(): mixed
    {
        $headers = Yii::$app->getRequest()->getHeaders();

        $requestHeaders = [];
        $hasCensorList = count($this->censoredVariableNames);

        foreach ($headers as $name => $value) {
            if ($hasCensorList && in_array($name, $this->censoredVariableNames, true)) {
                $value = $this->censorString;
            }

            if (is_array($value) && count($value) === 1) {
                $requestHeaders[$name] = current($value);
            } else {
                $requestHeaders[$name] = $value;
            }
        }

        $responseHeaders = [];

        foreach (headers_list() as $header) {
            if (($pos = strpos($header, ':')) !== false) {
                $name = substr($header, 0, $pos);

                if ($hasCensorList && in_array($name, $this->censoredVariableNames, true)) {
                    $value = $this->censorString;
                } else {
                    $value = trim(substr($header, $pos + 1));
                }

                if (isset($responseHeaders[$name])) {
                    if (!is_array($responseHeaders[$name])) {
                        $responseHeaders[$name] = [$responseHeaders[$name], $value];
                    } else {
                        $responseHeaders[$name][] = $value;
                    }
                } else {
                    $responseHeaders[$name] = $value;
                }
            } else {
                $responseHeaders[] = $header;
            }
        }

        if (Yii::$app->requestedAction) {
            if (Yii::$app->requestedAction instanceof InlineAction) {
                $action = get_class(Yii::$app->requestedAction->controller) . '::' . Yii::$app->requestedAction->actionMethod . '()';
            } else {
                $action = get_class(Yii::$app->requestedAction) . '::run()';
            }
        } else {
            $action = null;
        }

        $data = [
            'flashes' => $this->getFlashes(),
            'statusCode' => Yii::$app->getResponse()->getStatusCode(),
            'requestHeaders' => $requestHeaders,
            'responseHeaders' => $responseHeaders,
            'route' => Yii::$app->requestedAction ? Yii::$app->requestedAction->getUniqueId() : Yii::$app->requestedRoute,
            'action' => $action,
            'actionParams' => Yii::$app->requestedParams,
            'general' => [
                'method' => Yii::$app->getRequest()->getMethod(),
                'isAjax' => Yii::$app->getRequest()->getIsAjax(),
                'isPjax' => Yii::$app->getRequest()->getIsPjax(),
                'isFlash' => Yii::$app->getRequest()->getIsFlash(),
                'isSecureConnection' => Yii::$app->getRequest()->getIsSecureConnection(),
            ],
            'requestBody' => Yii::$app->getRequest()->getRawBody() == '' ? [] : [
                'Content Type' => Yii::$app->getRequest()->getContentType(),
                'Raw' => Yii::$app->getRequest()->getRawBody(),
                'Decoded' => Yii::$app->getRequest()->getBodyParams(),
            ],
        ];

        foreach ($this->displayVars as $name) {
            $data[trim($name, '_')] = empty($GLOBALS[$name]) ? [] : $GLOBALS[$name];
        }

        return $this->censorArray($data);
    }

    /**
     * Getting flash messages without deleting them or touching deletion counters
     *
     * @throws InvalidConfigException
     *
     * @return array flash messages (key => message).
     */
    protected function getFlashes(): array
    {
        /* @var Session $session */
        $session = Yii::$app->has('session', true) ? Yii::$app->get('session') : null;

        if ($session === null || !$session->getIsActive()) {
            return [];
        }

        $counters = $session->get($session->flashParam, []);
        $flashes = [];

        foreach (array_keys($counters) as $key) {
            if (array_key_exists($key, $_SESSION)) {
                $flashes[$key] = $_SESSION[$key];
            }
        }
        return $flashes;
    }

    /**
     * @param array $data
     *
     * @throws Exception
     *
     * @return array
     */
    protected function censorArray(array $data): array
    {
        if (empty($this->censoredVariableNames) || empty($data)) {
            return $data;
        }

        foreach ($this->censoredVariableNames as $var) {
            $key = ltrim($var, '_');
            if (ArrayHelper::getValue($data, $key) !== null) {
                ArrayHelper::setValue($data, $key, $this->censorString);
                if (str_starts_with($key, 'requestBody')) {
                    ArrayHelper::setValue($data, 'requestBody.Raw', $this->censorString);
                }
            }
        }

        return $data;
    }
}
