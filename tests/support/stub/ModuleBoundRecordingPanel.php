<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\debug\Panel;

/**
 * Stub panel that records {@see Panel::moduleBound()} invocations and whether the module reference was already bound
 * at call time.
 */
final class ModuleBoundRecordingPanel extends Panel
{
    public int $moduleBoundCalls = 0;
    public bool $moduleBoundWithModule = false;

    public function moduleBound(): void
    {
        $this->moduleBoundCalls++;

        $this->moduleBoundWithModule = $this->module !== null;
    }
}
