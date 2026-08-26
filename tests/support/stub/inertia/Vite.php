<?php

declare(strict_types=1);

namespace yii\inertia;

use yii\base\Component;

/**
 * Stand-in for the `yii2-extensions/inertia` Vite bridge, loaded only when the real package is absent.
 */
final class Vite extends Component
{
    public string $baseUrl = '@web/build';
    public bool $devMode = false;
    public string|null $devServerUrl = null;
    /**
     * @var list<string> Entry points passed to the Vite build.
     */
    public array $entrypoints = [];
    public bool $includeViteClient = true;
    public string $manifestPath = '';
    public bool $modulePreload = true;
}
