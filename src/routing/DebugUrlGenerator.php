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
    private string $moduleId;

    public function __construct(string $moduleId = 'debug')
    {
        $moduleId = trim($moduleId, '/');

        $this->moduleId = $moduleId !== '' ? $moduleId : 'debug';
    }

    /**
     * Builds a debugger action URL while keeping the captured tag authoritative.
     */
    public function action(string $action, string $tag, array $queryParams = []): string
    {
        $action = trim($action, '/');

        return Url::toRoute(
            ["/{$this->moduleId}/{$action}", 'tag' => $tag] + self::query($queryParams),
        );
    }

    /**
     * Builds the canonical request-history URL used by existing Yii2 integrations.
     */
    public function history(array $queryParams = []): string
    {
        return Url::toRoute(["/{$this->moduleId}/index"] + self::query($queryParams));
    }

    /**
     * Builds a panel URL while keeping the captured tag and target panel authoritative.
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
     * @param array<array-key, mixed> $queryParams
     *
     * @return array<array-key, mixed>
     */
    private static function query(array $queryParams): array
    {
        unset($queryParams[0], $queryParams['tag'], $queryParams['panel']);

        return $queryParams;
    }
}
