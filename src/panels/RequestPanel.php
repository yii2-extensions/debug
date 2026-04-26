<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Yii;
use yii\base\InlineAction;
use yii\debug\Panel;
use yii\helpers\ArrayHelper;
use yii\web\{Response, Session};

use function array_key_exists;
use function count;
use function get_class;
use function in_array;
use function is_array;
use function is_int;
use function is_string;

/**
 * Debugger panel that collects and displays request data.
 */
class RequestPanel extends Panel
{
    /**
     * @var array<int, string> List of variable names which values should be censored in the output.
     */
    public array $censoredVariableNames = [];
    /**
     * Value to display instead of the variable value if the name is on the censor list
     */
    public string $censorString = '****';
    /**
     * @var array<int, string> List of the PHP predefined variables that are allowed to be displayed in the request
     * panel.
     * Note that a variable must be accessible via `$GLOBALS`. Otherwise it won't be displayed.
     */
    public array $displayVars = [
        '_COOKIE',
        '_FILES',
        '_GET',
        '_POST',
        '_SERVER',
        '_SESSION',
    ];

    public function getDetail(): string
    {
        return Yii::$app->view->render('panels/request/detail', ['panel' => $this]);
    }

    public function getName(): string
    {
        return 'Request';
    }

    public function getSummary(): string
    {
        return Yii::$app->view->render('panels/request/summary', ['panel' => $this]);
    }

    public function getToolbarIcon(): string
    {
        return 'request';
    }

    /**
     * @return array<string, mixed>
     */
    public function save(): array
    {
        $headers = Yii::$app->getRequest()->getHeaders();

        $requestHeaders = [];

        $hasCensorList = $this->censoredVariableNames !== [];

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

        $requestedAction = Yii::$app->requestedAction;

        if ($requestedAction !== null) {
            if ($requestedAction instanceof InlineAction) {
                $action = get_class($requestedAction->controller) . '::' . $requestedAction->actionMethod . '()';
            } else {
                $action = get_class($requestedAction) . '::run()';
            }
        } else {
            $action = null;
        }

        $data = [
            'action' => $action,
            'actionParams' => Yii::$app->requestedParams,
            'flashes' => $this->getFlashes(),
            'general' => [
                'isAjax' => Yii::$app->getRequest()->getIsAjax(),
                'isFlash' => Yii::$app->getRequest()->getIsFlash(),
                'isPjax' => Yii::$app->getRequest()->getIsPjax(),
                'isSecureConnection' => Yii::$app->getRequest()->getIsSecureConnection(),
                'method' => Yii::$app->getRequest()->getMethod(),
            ],
            'requestBody' => Yii::$app->getRequest()->getRawBody() === '' ? [] : [
                'Content Type' => Yii::$app->getRequest()->getContentType(),
                'Decoded' => Yii::$app->getRequest()->getBodyParams(),
                'Raw' => Yii::$app->getRequest()->getRawBody(),
            ],

            'requestHeaders' => $requestHeaders,
            'responseHeaders' => $responseHeaders,
            'route' => $requestedAction !== null ? $requestedAction->getUniqueId() : Yii::$app->requestedRoute,
            'statusCode' => Yii::$app->getResponse()->getStatusCode(),
        ];

        foreach ($this->displayVars as $name) {
            $data[trim($name, '_')] = self::normalizeGlobalValue($GLOBALS[$name] ?? null);
        }

        return $this->censorArray($data);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function censorArray(array $data): array
    {
        if ($this->censoredVariableNames === [] || $data === []) {
            return $data;
        }

        foreach ($this->censoredVariableNames as $var) {
            $key = ltrim($var, '_');

            if (ArrayHelper::getValue($data, $key) !== null) {
                ArrayHelper::setValue($data, $key, $this->censorString);

                if (strpos($key, 'requestBody') === 0) {
                    ArrayHelper::setValue($data, 'requestBody.Raw', $this->censorString);
                }
            }
        }

        return self::normalizeTopLevelData($data);
    }

    /**
     * Getting flash messages without deleting them or touching deletion counters
     *
     * @return array<int|string, mixed> Flash messages (key => message).
     */
    protected function getFlashes(): array
    {
        $session = Yii::$app->has('session', true) ? Yii::$app->get('session', false) : null;

        if (!$session instanceof Session || !$session->getIsActive()) {
            return [];
        }

        $counters = $session->get($session->flashParam, []);

        if (!is_array($counters)) {
            return [];
        }

        $sessionData = $_SESSION;
        $flashes = [];

        foreach (array_keys($counters) as $key) {
            if (array_key_exists($key, $sessionData)) {
                $flashes[$key] = $sessionData[$key];
            }
        }
        return $flashes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getToolbarItems(): array
    {
        $statusCode = $this->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            $status = 'success';
        } elseif ($statusCode >= 300 && $statusCode < 400) {
            $status = 'info';
        } else {
            $status = 'danger';
        }

        $statusText = Response::$httpStatuses[$statusCode] ?? '';

        $statusText = is_string($statusText) ? $statusText : '';

        return [
            [
                'status' => $status,
                'title' => "Status code: $statusCode $statusText",
                'value' => $statusCode,
            ],
        ];
    }

    private function getStatusCode(): int
    {
        $data = is_array($this->data) ? $this->data : [];

        $statusCode = $data['statusCode'] ?? 200;

        if (is_int($statusCode)) {
            return $statusCode;
        }

        if (is_numeric($statusCode)) {
            return (int) $statusCode;
        }

        return 200;
    }

    private static function normalizeGlobalValue(mixed $value): mixed
    {
        if ($value === null || $value === false || $value === '' || $value === [] || $value === 0 || $value === '0') {
            return [];
        }

        return $value;
    }

    /**
     * @param array<int|string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function normalizeTopLevelData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
