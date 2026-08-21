<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use Override;
use yii\base\View;

/**
 * View fixture that captures render arguments without evaluating a template.
 */
final class CapturingView extends View
{
    public object|null $renderContext = null;
    /** @var array<mixed, mixed> */
    public array $renderParams = [];
    public string $renderView = '';

    /**
     * @param string $view
     * @param array<mixed, mixed> $params
     * @param object|null $context
     */
    #[Override]
    public function render($view, $params = [], $context = null): string
    {
        $this->renderContext = $context;
        $this->renderParams = $params;
        $this->renderView = $view;

        return 'rendered';
    }
}
