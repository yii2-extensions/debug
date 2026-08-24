<?php

declare(strict_types=1);

namespace yii\debug\tests\inertia;

use PHPForge\Debug\Helper\CellMore;
use PHPForge\Debug\Panel\Inertia\InertiaSnapshot;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\InertiaPanel;
use yii\debug\tests\support\TestCase;
use yii\inertia\Manager;

/**
 * Unit tests for {@see InertiaPanel} covering the component-gated enablement, the per-capture sidebar activation, and
 * the detail/toolbar rendering.
 */
#[Group('panel')]
#[Group('inertia')]
final class InertiaPanelTest extends TestCase
{
    public function testGetDetailCollapsesThePropsTableOnlyOnceItGrowsTall(): void
    {
        $short = $this->renderPropsTable(CellMore::ROW_THRESHOLD);
        $tall = $this->renderPropsTable(CellMore::ROW_THRESHOLD + 1);

        self::assertStringNotContainsString(
            'yii-debug-cell-more',
            $short,
            'A table at the threshold must stay fully visible.',
        );
        self::assertStringContainsString(
            'yii-debug-cell-more',
            $tall,
            'One row past the threshold must fold the table.',
        );
        self::assertStringContainsString(
            'prop' . CellMore::ROW_THRESHOLD,
            $tall,
            'Folding must keep every row in the markup.',
        );
    }

    public function testGetDetailExposesTheExpandAffordanceOnAFoldedPropsTable(): void
    {
        $html = $this->renderPropsTable(CellMore::ROW_THRESHOLD + 1);

        self::assertStringContainsString(
            'data-yii-debug-toggle="cell-more"',
            $html,
            'The toggle must carry the delegation hook that swaps the label.',
        );
        self::assertStringContainsString(
            'aria-expanded="false"',
            $html,
            'A folded table must announce itself as collapsed.',
        );
        self::assertStringContainsString(
            'Show more',
            $html,
            'The collapsed toggle must invite expansion.',
        );
    }

    public function testGetDetailKeepsLongPropValuesInspectableBehindTheClamp(): void
    {
        $panel = $this->makePanel(
            InertiaPanel::class,
        );

        $needle = str_repeat('z', 700);

        $this->hydratePanel(
            $panel,
            InertiaSnapshot::capture(
                null,
                [
                    'component' => 'site/index',
                    'props' => ['blob' => $needle, 'short' => 'ok'],
                    'url' => '/site/index',
                    'version' => 'v1',
                ],
                ['X-Inertia' => 'true'],
                [],
                200,
            ),
        );

        $html = $panel->getDetail();

        self::assertStringContainsString(
            $needle,
            $html,
            'The value must survive whole instead of being cut server side.',
        );
        self::assertStringNotContainsString(
            '…',
            $html,
            'No ellipsis may stand in for dropped content.',
        );
        self::assertStringContainsString(
            'yii-debug-cell-payload',
            $html,
            'Prop values must claim the wrapping payload cell.',
        );
    }

    public function testGetDetailMarksSharedAndPageProps(): void
    {
        $panel = $this->makePanel(
            InertiaPanel::class,
        );

        $this->hydratePanel(
            $panel,
            InertiaSnapshot::capture(
                null,
                [
                    'component' => 'site/index',
                    'props' => [
                        'auth' => ['isGuest' => true],
                        'post' => ['id' => 7],
                    ],
                    'url' => '/site/index',
                    'version' => 'v1',
                ],
                [],
                ['auth'],
                200,
            ),
        );

        $html = $panel->getDetail();

        self::assertStringContainsString(
            '>shared</span>',
            $html,
            "Config-shared props must wear the 'shared' badge.",
        );
        self::assertStringContainsString(
            '>page</span>',
            $html,
            "Page-specific props must wear the 'page' badge.",
        );
    }

    public function testGetDetailRendersComponentAndProps(): void
    {
        $panel = $this->makePanel(
            InertiaPanel::class,
        );

        $this->hydratePanel(
            $panel,
            InertiaSnapshot::capture(
                null,
                [
                    'component' => 'site/index',
                    'props' => ['user' => ['id' => 1]],
                    'url' => '/site/index',
                    'version' => 'v1',
                ],
                ['X-Inertia' => 'true'],
                [],
                200,
            ),
        );

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'site/index',
            $html,
            'Component name must surface in the detail.',
        );
        self::assertStringContainsString(
            'Props',
            $html,
            'Props section heading must be present.',
        );
        self::assertStringContainsString(
            '"user"',
            $html,
            'Prop keys must surface in the JSON block.',
        );
        self::assertStringContainsString(
            'Inertia visit',
            $html,
            "XHR captures must read as 'Inertia visit'.",
        );
    }

    public function testGetDetailRendersEmptyStateWhenPageMissing(): void
    {
        $panel = $this->makePanel(
            InertiaPanel::class,
        );

        $this->hydratePanel(
            $panel,
            InertiaSnapshot::capture(
                null,
                null,
                [],
                [],
                200,
            ),
        );

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'yii-debug-empty-state',
            $html,
            'Missing page must render the empty-state card.',
        );
        self::assertStringContainsString(
            'No Inertia page in this request',
            $html,
            'Card headline must describe the missing page.',
        );
    }

    public function testGetDetailRendersMessageWhenPageHasNoProps(): void
    {
        $panel = $this->makePanel(
            InertiaPanel::class,
        );

        $this->hydratePanel(
            $panel,
            InertiaSnapshot::capture(
                null,
                [
                    'component' => 'site/index',
                    'props' => [],
                    'url' => '/',
                    'version' => 'v1',
                ],
                [],
                [],
                200,
            ),
        );

        self::assertStringContainsString(
            'The page rendered without props.',
            $panel->getDetail(),
            'An empty page payload must render the no-props message.',
        );
    }

    public function testGetDetailRendersScalarPropTypesHeadersAndLongPayload(): void
    {
        $panel = $this->makePanel(
            InertiaPanel::class,
        );

        $this->hydratePanel(
            $panel,
            InertiaSnapshot::capture(
                null,
                [
                    'component' => 'site/index',
                    'props' => [
                        'string' => str_repeat('a', 700),
                        'integer' => 7,
                        'float' => 1.5,
                        'boolean' => true,
                        'null' => null,
                    ],
                    'url' => '/site/index',
                    'version' => 'v1',
                ],
                [
                    'X-Inertia' => 'true',
                    'X-Inertia-Partial-Data' => 'string,integer',
                ],
                [],
                200,
            ),
        );

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'Partial reload',
            $html,
            'Partial request headers must classify the visit.',
        );
        self::assertStringContainsString(
            'X-Inertia-Partial-Data',
            $html,
            'Negotiation headers must be listed.',
        );
        self::assertStringContainsString(
            'string(700)',
            $html,
            'String props must include their length.',
        );
        self::assertMatchesRegularExpression(
            '/>\s*int\s*</',
            $html,
            'Integer props must expose their type.',
        );
        self::assertMatchesRegularExpression(
            '/>\s*float\s*</',
            $html,
            'Float props must expose their type.',
        );
        self::assertMatchesRegularExpression(
            '/>\s*bool\s*</',
            $html,
            'Boolean props must expose their type.',
        );
        self::assertMatchesRegularExpression(
            '/>\s*null\s*</',
            $html,
            'Null props must expose their type.',
        );
        self::assertStringContainsString(
            'yii-debug-cell-more',
            $html,
            'Long raw payloads must use the expandable wrapper.',
        );
    }

    public function testGetDetailRendersVersionConflictWhenStatusIs409(): void
    {
        $panel = $this->makePanel(
            InertiaPanel::class
        );

        $this->hydratePanel(
            $panel,
            InertiaSnapshot::capture(
                'http://example.test/site/index',
                null,
                [
                    'X-Inertia' => 'true',
                    'X-Inertia-Version' => 'stale',
                ],
                [],
                409,
            ),
        );

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'Version conflict interrupted this visit',
            $html,
            'Conflict headline must surface.',
        );
        self::assertStringContainsString(
            'http://example.test/site/index',
            $html,
            'Reload target must echo the `X-Inertia-Location` header.',
        );
    }

    public function testGetNameAndIcon(): void
    {
        $panel = $this->makePanel(
            InertiaPanel::class,
        );

        self::assertSame(
            'Inertia',
            $panel->getName(),
            "Display name must be 'Inertia'.",
        );
        self::assertSame(
            'inertia',
            $panel->getToolbarIcon(),
            "Icon key must be 'inertia'.",
        );
    }

    public function testGetStatusCodeReturnsZeroBeforeHydration(): void
    {
        $panel = $this->makePanel(
            InertiaPanel::class,
        );

        self::assertSame(
            0,
            $panel->getStatusCode(),
            'An un-hydrated panel must expose the neutral status code.',
        );
    }

    public function testGetToolbarItemsCarryComponentName(): void
    {
        $panel = $this->makePanel(
            InertiaPanel::class,
        );

        $this->hydratePanel(
            $panel,
            InertiaSnapshot::capture(
                null,
                [
                    'component' => 'site/index',
                    'props' => [],
                    'url' => '/site/index',
                    'version' => 'v1',
                ],
                [],
                [],
                200,
            ),
        );

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertSame(
            [
                [
                    'title' => 'Inertia component',
                    'value' => 'site/index',
                ],
            ],
            $items,
            'Chip must carry the component name.',
        );
    }

    public function testGetToolbarItemsReturnEmptyListWithoutCapturedPage(): void
    {
        $panel = $this->makePanel(
            InertiaPanel::class,
        );

        $this->hydratePanel(
            $panel,
            InertiaSnapshot::capture(null, null, [], [], 200),
        );

        self::assertSame(
            [],
            $this->invoke($panel, 'getToolbarItems'),
            'Missing page must skip the toolbar chip.',
        );
    }

    public function testHasContentReturnsFalseForPlainCapture(): void
    {
        $panel = $this->makePanel(
            InertiaPanel::class,
        );

        $this->hydratePanel(
            $panel,
            InertiaSnapshot::capture(
                null,
                null,
                [],
                [],
                200,
            ),
        );

        self::assertFalse(
            $panel->hasContent(),
            'Plain captures must hide the sidebar entry.',
        );
    }

    public function testHasContentReturnsTrueForCapturedPage(): void
    {
        $panel = $this->makePanel(
            InertiaPanel::class,
        );

        $this->hydratePanel(
            $panel,
            InertiaSnapshot::capture(
                null,
                ['component' => 'site/index', 'props' => [], 'url' => '/', 'version' => 'v1'],
                [],
                [],
                200,
            ),
        );

        self::assertTrue(
            $panel->hasContent(),
            'Captured page must surface the sidebar entry.',
        );
    }

    public function testHasContentReturnsTrueForInertiaXhrWithoutPage(): void
    {
        $panel = $this->makePanel(
            InertiaPanel::class,
        );

        $this->hydratePanel(
            $panel,
            InertiaSnapshot::capture(
                'http://example.test/',
                null,
                ['X-Inertia' => 'true', 'X-Inertia-Version' => 'stale'],
                [],
                409,
            ),
        );

        self::assertTrue(
            $panel->hasContent(),
            'Version-conflict XHR must surface the sidebar entry.',
        );
    }

    public function testIsEnabledReturnsFalseWhenInertiaComponentCannotBeCreated(): void
    {
        $panel = $this->makePanel(
            InertiaPanel::class,
            ['inertia' => ['class' => 'missing\\InertiaManager']],
        );

        self::assertFalse(
            $panel->isEnabled(),
            'An invalid Inertia component definition must disable the panel.',
        );
    }

    public function testIsEnabledReturnsFalseWithoutInertiaComponent(): void
    {
        $panel = $this->makePanel(
            InertiaPanel::class,
        );

        self::assertFalse(
            $panel->isEnabled(),
            'Missing `inertia` component must disable the panel.',
        );
    }

    public function testIsEnabledReturnsTrueWhenManagerIsRegistered(): void
    {
        $panel = $this->makePanel(
            InertiaPanel::class,
            ['inertia' => ['class' => Manager::class]],
        );

        self::assertTrue(
            $panel->isEnabled(),
            'Registered manager must enable the panel.',
        );
    }

    public function testSnapshotDataReturnsEveryCapturedField(): void
    {
        $snapshot = InertiaSnapshot::capture(
            '/users',
            ['component' => 'Users'],
            ['X-Inertia' => 'true'],
            ['auth'],
            201,
        );

        self::assertSame(
            [
                'location' => '/users',
                'page' => ['component' => 'Users'],
                'requestHeaders' => ['X-Inertia' => 'true'],
                'sharedKeys' => ['auth'],
                'statusCode' => 201,
            ],
            $snapshot->data(),
            'The display payload must retain every captured Inertia field.',
        );
    }

    /**
     * Renders the detail for a page carrying `$count` trivially small props.
     *
     * @param int $count Number of props to ship.
     */
    private function renderPropsTable(int $count): string
    {
        $props = [];

        for ($i = 0; $i < $count; $i++) {
            $props["prop{$i}"] = $i;
        }

        $panel = $this->makePanel(
            InertiaPanel::class,
        );

        $this->hydratePanel(
            $panel,
            InertiaSnapshot::capture(
                null,
                [
                    'component' => 'site/index',
                    'props' => $props,
                    'url' => '/site/index',
                    'version' => 'v1',
                ],
                ['X-Inertia' => 'true'],
                [],
                200,
            ),
        );

        return $panel->getDetail();
    }
}
