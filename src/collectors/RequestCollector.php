<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Panel\Request\RequestSnapshot;
use Yii;
use yii\base\InlineAction;
use yii\helpers\ArrayHelper;
use yii\web\Session;

use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function is_string;

/**
 * Captures the HTTP request and response state for the Request panel.
 */
class RequestCollector extends Collector
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
     * @var array<int, string> PHP predefined variables that the collector may surface.
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

    private CapturePolicy|null $capturePolicy = null;

    /**
     * Snapshots the request/response state: action, route, headers, body, status code, flash messages, and the
     * configured superglobals.
     *
     * Header names listed in {@see $censoredVariableNames} are emitted with {@see $censorString} instead of their real
     * value; the same masking is applied to top-level keys in the captured payload via {@see censorArray()}.
     *
     * @return RequestSnapshot|null Captured request payload; `null` when the collector never started.
     */
    public function capture(): RequestSnapshot|null
    {
        if (!$this->isStarted()) {
            return null;
        }

        $request = Yii::$app->getRequest();

        $headers = $request->getHeaders();

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

        $rawBody = $request->getRawBody();
        $requestBody = $rawBody === '' ? [] : $this->capturePolicy()->redactBody($rawBody, $request->getBodyParams());

        $data = [
            'action' => $action,
            'actionParams' => Yii::$app->requestedParams,
            'flashes' => $this->getFlashes(),
            'general' => [
                'isAjax' => $request->getIsAjax(),
                'isFlash' => $request->getIsFlash(),
                'isPjax' => $request->getIsPjax(),
                'isSecureConnection' => $request->getIsSecureConnection(),
                'method' => $request->getMethod(),
            ],
            'requestBody' => $requestBody === [] ? [] : [
                'Content Type' => $request->getContentType(),
                'Decoded' => $requestBody['decoded'],
                'Raw' => $requestBody['raw'],
            ],
            'requestHeaders' => $requestHeaders,
            'responseHeaders' => $responseHeaders,
            'route' => $requestedAction !== null ? $requestedAction->getUniqueId() : Yii::$app->requestedRoute,
            'statusCode' => Yii::$app->getResponse()->getStatusCode(),
        ];

        foreach ($this->displayVars as $name) {
            $data[trim($name, '_')] = self::normalizeGlobalValue($GLOBALS[$name] ?? null);
        }

        return RequestSnapshot::capture($this->applyConfiguredCensors($this->capturePolicy()->redact($data)));
    }

    /**
     * Returns the stable ID pairing this collector with the Request panel.
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'request';
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
        foreach ($this->censoredVariableNames as $var) {
            $key = ltrim($var, '_');

            if (ArrayHelper::getValue($data, $key) !== null) {
                ArrayHelper::setValue($data, $key, $this->censorString);

                if (str_starts_with($key, 'requestBody')) {
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
        $session = Yii::$app->get('session', false);

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
     * Aggregates a raw response-header list into a name → value map, merging duplicates into arrays and masking entries
     * whose name appears in {@see $censoredVariableNames}.
     *
     * @param array<int, string> $rawHeaders Header lines in `Name: value` form, as returned by `headers_list()`; bare
     * strings without a colon are kept verbatim at int-keyed slots.
     *
     * @return array<int|string, array<int, string>|string> Aggregated header map with masked values.
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
     * Reapplies explicitly configured markers after the mandatory default policy has sanitized the payload.
     *
     * @param array<string, mixed> $data Default-sanitized request payload.
     *
     * @return array<string, mixed> Sanitized payload retaining the configured censor marker.
     */
    private function applyConfiguredCensors(array $data): array
    {
        $data = $this->censorArray($data);

        foreach (['requestHeaders', 'responseHeaders'] as $section) {
            if (!is_array($data[$section] ?? null)) {
                continue;
            }

            foreach ($this->censoredVariableNames as $name) {
                if (array_key_exists($name, $data[$section])) {
                    $data[$section][$name] = $this->censorString;
                }
            }
        }

        return $data;
    }

    /**
     * Returns the shared default policy used for all persistent request data.
     */
    private function capturePolicy(): CapturePolicy
    {
        return $this->capturePolicy ??= $this->module?->createCapturePolicy() ?? new CapturePolicy();
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
