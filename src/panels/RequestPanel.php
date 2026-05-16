<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Yii;
use yii\base\InlineAction;
use yii\debug\controllers\DefaultController;
use yii\debug\Panel;
use yii\debug\panels\request\RequestDataNormalizer;
use yii\helpers\ArrayHelper;
use yii\web\{Response, Session};

use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_string;

/**
 * Captures the HTTP request and response state and renders them in the Request panel.
 *
 * Snapshots the routing target, request/response headers, status code, body, flash messages, and the configured PHP
 * superglobals, with optional value censoring for sensitive keys.
 *
 * @extends Panel<array<string, mixed>>
 */
class RequestPanel extends Panel
{
    /**
     * @var array<int, string> Variable names whose values should be replaced with `$censorString` in the captured
     * snapshot.
     */
    public array $censoredVariableNames = [];
    /**
     * Replacement value emitted for variables listed in {@see $censoredVariableNames}.
     */
    public string $censorString = '****';
    /**
     * @var array<int, string> PHP predefined variables that the panel may surface.
     *
     * Each variable must be accessible via `$GLOBALS`; otherwise it is silently skipped.
     */
    public array $displayVars = [
        '_COOKIE',
        '_FILES',
        '_GET',
        '_POST',
        '_SERVER',
        '_SESSION',
    ];

    /**
     * Renders the detail view with the request hero header and the per-tab sections.
     */
    public function getDetail(): string
    {
        $controller = Yii::$app->controller;

        $summary = $controller instanceof DefaultController ? $controller->summary : [];

        $view = RequestDataNormalizer::fromPanelData($this->data, $summary);

        return Yii::$app->view->render(
            'panels/request/detail',
            ['view' => $view],
            $this,
        );
    }

    /**
     * Returns the panel display name.
     */
    public function getName(): string
    {
        return 'Request';
    }

    /**
     * Renders the toolbar summary chip.
     */
    public function getSummary(): string
    {
        return Yii::$app->view->render(
            'panels/request/summary',
            ['panel' => $this],
            $this,
        );
    }

    /**
     * Returns the toolbar icon name.
     */
    public function getToolbarIcon(): string
    {
        return 'request';
    }

    /**
     * Snapshots the request/response state: action, route, headers, body, status code, flash messages, and the
     * configured superglobals.
     *
     * Header names listed in {@see $censoredVariableNames} are emitted with {@see $censorString} instead of their real
     * value; the same masking is applied to top-level keys in the captured payload via {@see censorArray()}.
     *
     * @return array<string, mixed> Captured request payload consumed by the detail view.
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

        $responseHeaders = $this->normalizeResponseHeaders(headers_list());

        $requestedAction = Yii::$app->requestedAction;

        if ($requestedAction === null) {
            $action = null;
        } elseif ($requestedAction instanceof InlineAction && $requestedAction->controller !== null) {
            $action = $requestedAction->controller::class . '::' . $requestedAction->actionMethod . '()';
        } else {
            $action = $requestedAction::class . '::run()';
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
     * Replaces the values of any {@see $censoredVariableNames} entries with {@see $censorString}, returning the
     * sanitized top-level data.
     *
     * Also masks `requestBody.Raw` whenever a `requestBody.*` key is censored, so the verbatim payload does not leak
     * the censored field by accident.
     *
     * @param array<string, mixed> $data Captured request payload.
     *
     * @return array<string, mixed> Sanitized payload with masked values.
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
     * Returns the active flash messages without deleting them or touching the deletion counters.
     *
     * @return array<int|string, mixed> Flash messages keyed by their session flash name.
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
     * Builds the toolbar item with the response status code, colored by class (`success` for 2xx, `info` for 3xx,
     * `danger` for everything else).
     *
     * @return array<int, array<string, mixed>> Single-element list with the status chip.
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

    /**
     * Aggregates a raw response-header list into a name → value map, merging duplicates into arrays and masking entries
     * whose name appears in {@see $censoredVariableNames}.
     *
     * @param array<int, string> $rawHeaders Header lines in `Name: value` form, as returned by `headers_list()`; bare
     * strings without a colon are kept verbatim at int-keyed slots.
     *
     * @return array<int|string, string|array<int, string>> Aggregated header map with masked values.
     */
    protected function normalizeResponseHeaders(array $rawHeaders): array
    {
        $responseHeaders = [];
        $hasCensorList = $this->censoredVariableNames !== [];

        foreach ($rawHeaders as $header) {
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

        return $responseHeaders;
    }

    /**
     * Returns the saved response status code, narrowed to an int, defaulting to `200` when missing or non-numeric.
     */
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

    /**
     * Collapses every "empty" superglobal value (`null`, `false`, `''`, `[]`, `0`, `'0'`) to `[]`, so the renderer
     * always sees an iterable shape.
     */
    private static function normalizeGlobalValue(mixed $value): mixed
    {
        if ($value === null || $value === false || $value === '' || $value === [] || $value === 0 || $value === '0') {
            return [];
        }

        return $value;
    }

    /**
     * Narrows the captured payload to string-keyed entries only, dropping any int-keyed leftovers introduced by
     * {@see ArrayHelper::setValue()} edge cases.
     *
     * @param array<int|string, mixed> $data Captured payload.
     *
     * @return array<string, mixed> Payload restricted to string keys.
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
