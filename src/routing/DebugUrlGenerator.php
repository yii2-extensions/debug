<?php

declare(strict_types=1);

namespace yii\debug\routing;

use PHPForge\Debug\Routing\DebugUrlGeneratorInterface;
use yii\helpers\Url;

use function trim;

/**
 * Yii URL-manager adapter for the framework-neutral debugger URL contract.
 */
final readonly class DebugUrlGenerator implements DebugUrlGeneratorInterface
{
    /**
     * Unique id of the debugger module, normalized without surrounding slashes.
     */
    private string $moduleId;

    /**
     * @param string $moduleId Unique id of the debugger module; falls back to `'debug'` when empty.
     */
    public function __construct(string $moduleId = 'debug')
    {
        $moduleId = trim($moduleId, '/');

        $this->moduleId = $moduleId !== '' ? $moduleId : 'debug';
    }

    /**
     * Builds a panel URL while keeping the captured tag and target panel authoritative.
     *
     * @param string $tag Capture to open.
     * @param string $panel Panel to open within that capture.
     * @param array<array-key, mixed> $queryParams Extra query parameters; route-owned keys are ignored.
     *
     * @return string Absolute URL of the panel view.
     */
    public function panel(string $tag, string $panel, array $queryParams = []): string
    {
        return Url::toRoute(
            ["/{$this->moduleId}/view", 'tag' => $tag, 'panel' => $panel] + self::query($queryParams),
        );
    }

    /**
     * Removes route-owned keys before additional query parameters are merged.
     *
     * @param array<array-key, mixed> $queryParams Extra query parameters supplied by the caller.
     *
     * @return array<array-key, mixed> Parameters without the route-owned keys.
     */
    private static function query(array $queryParams): array
    {
        unset($queryParams[0], $queryParams['tag'], $queryParams['panel']);

        return $queryParams;
    }
}
