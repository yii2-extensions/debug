<?php

declare(strict_types=1);

namespace yii\debug\tests\actions;

use PHPUnit\Framework\Attributes\{Group, TestWith};
use yii\debug\actions\PhpInfoAction;
use yii\debug\tests\support\ActionTestCase;

/**
 * Unit tests for {@see PhpInfoAction} covering `run` rendering of the PHP information view.
 */
#[Group('actions')]
final class PhpInfoActionTest extends ActionTestCase
{
    public function testActionPhpInfoRendersPhpInfoView(): void
    {
        $module = $this->bootDebugModule();

        $html = $this->runDebugAction(
            new PhpInfoAction('php-info'),
            $module,
        );

        self::assertNotSame(
            '',
            $html,
            "'phpinfo' view must render markup.",
        );
    }

    /**
     * @param class-string $class Library class exposing the stable entry point.
     * @param 'public'|'protected' $visibility Access required by consumers or subclasses.
     */
    #[TestWith([PhpInfoAction::class, 'run', 'public'])]
    public function testExtensionEntryPointsRetainVisibility(string $class, string $method, string $visibility): void
    {
        $entry = new \ReflectionMethod($class, $method);

        self::assertTrue(
            $visibility === 'public' ? $entry->isPublic() : $entry->isProtected(),
            "{$class}::{$method} must retain {$visibility} access for consuming applications.",
        );
    }

    public function testPhpInfoHydratesTheNewestCaptureForItsSidebar(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot($module, 'synthetic-newest', []);

        $action = new PhpInfoAction('php-info');

        $this->runDebugAction($action, $module);

        self::assertNotNull(
            $action->summary,
            'The standalone page must load its newest sidebar capture.',
        );
        self::assertSame(
            'synthetic-newest',
            $action->summary->tag,
            'The loaded summary must correspond to the newest stored capture.',
        );
    }
}
