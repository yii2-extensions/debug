<?php

declare(strict_types=1);

namespace yii\debug\tests\request;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\request\{RequestHero, RequestSection, RequestSectionRenderer, RequestTab};
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see RequestSectionRenderer} covering the hero header, section rendering (filter affordance,
 * empty-state fallback, name/value table rows) and the tab navigation / panel wiring.
 */
#[Group('panel')]
#[Group('request')]
final class RequestSectionRendererTest extends TestCase
{
    public function testRenderHeroEmitsFlagChipsForEachActiveFlag(): void
    {
        $html = RequestSectionRenderer::renderHero(self::makeHero(flags: ['AJAX', 'HTTPS']));

        self::assertStringContainsString(
            '>AJAX</span>',
            $html,
            'AJAX flag must surface in the meta strip.',
        );
        self::assertStringContainsString(
            '>HTTPS</span>',
            $html,
            'HTTPS flag must surface in the meta strip.',
        );
    }

    public function testRenderHeroOmitsMethodPillWhenMethodIsEmpty(): void
    {
        $html = RequestSectionRenderer::renderHero(self::makeHero(method: ''));

        self::assertStringNotContainsString(
            'yii-debug-request-hero-method',
            $html,
            'Empty method must drop the method pill.',
        );
    }

    public function testRenderHeroOmitsStatusPillWhenStatusCodeIsZero(): void
    {
        $html = RequestSectionRenderer::renderHero(self::makeHero(statusCode: 0));

        self::assertStringNotContainsString(
            'yii-debug-snapshot-status',
            $html,
            'Zero status must drop the status pill.',
        );
    }

    public function testRenderHeroRendersStatusPillWithVariantModifier(): void
    {
        $html = RequestSectionRenderer::renderHero(self::makeHero(statusCode: 500, statusVariant: 'danger'));

        self::assertStringContainsString(
            'yii-debug-snapshot-status-danger',
            $html,
            'Variant must surface as a CSS modifier.',
        );
        self::assertStringContainsString(
            '>500</span>',
            $html,
            'Status code value must render inside the pill.',
        );
    }

    public function testRenderSectionEmitsFilterInputWhenFilterableAndNonEmpty(): void
    {
        $section = new RequestSection(caption: 'Server', entries: ['HTTP_HOST' => 'localhost'], filterable: true);
        $html = RequestSectionRenderer::renderSection($section);

        self::assertStringContainsString(
            'type="search"',
            $html,
            'Filterable section must expose a search input.',
        );
        self::assertStringContainsString(
            'data-yii-debug-filter-target',
            $html,
            'Filterable table must be the JS filter target.',
        );
    }

    public function testRenderSectionOmitsFilterInputWhenSectionIsEmpty(): void
    {
        $section = new RequestSection(caption: 'Server', entries: [], filterable: true);
        $html = RequestSectionRenderer::renderSection($section);

        self::assertStringNotContainsString(
            'type="search"',
            $html,
            'Empty section must not render the filter input.',
        );
        self::assertStringContainsString(
            'No data',
            $html,
            "Empty section must show the 'No data' placeholder.",
        );
    }

    public function testRenderSectionPicksHtmlSpecialCharsEscapingForRowValues(): void
    {
        $section = new RequestSection(caption: 'Headers', entries: ['X-Custom' => '<script>alert(1)</script>']);
        $html = RequestSectionRenderer::renderSection($section);

        self::assertStringNotContainsString(
            '<script>alert(1)</script>',
            $html,
            'Raw payload must never reach the rendered HTML.',
        );
        self::assertStringContainsString(
            '&lt;script&gt;',
            $html,
            'Tag characters must be escaped.',
        );
    }

    public function testRenderSectionRendersOneRowPerEntry(): void
    {
        $section = new RequestSection(caption: 'Headers', entries: ['a' => 'A', 'b' => 'B', 'c' => 'C']);
        $html = RequestSectionRenderer::renderSection($section);

        self::assertSame(
            3,
            substr_count($html, '<td>'),
            'Each entry must produce exactly one body row.',
        );
    }

    public function testRenderTabsMarksFirstTabActive(): void
    {
        $tabs = [
            new RequestTab(label: 'Parameters', sections: []),
            new RequestTab(label: 'Headers', sections: []),
        ];

        $html = RequestSectionRenderer::renderTabs($tabs);

        self::assertStringContainsString(
            'is-active',
            $html,
            "First tab must carry the 'is-active' class.",
        );
        self::assertStringContainsString(
            'aria-selected="true"',
            $html,
            "First tab anchor must have 'aria-selected=true'.",
        );
        self::assertStringContainsString(
            'aria-selected="false"',
            $html,
            "Subsequent tab anchors must have 'aria-selected=false'.",
        );
    }

    public function testRenderTabsWiresPanelIdsAndAriaControls(): void
    {
        $tabs = [
            new RequestTab(label: 'Parameters', sections: []),
            new RequestTab(label: 'Headers', sections: []),
        ];

        $html = RequestSectionRenderer::renderTabs($tabs);

        self::assertStringContainsString(
            'href="#r-tab-0"',
            $html,
            "First tab 'href' must point to 'r-tab-0'.",
        );
        self::assertStringContainsString(
            'aria-controls="r-tab-1"',
            $html,
            "Second tab 'aria-controls' must match its panel id.",
        );
        self::assertStringContainsString(
            'id="r-tab-0"',
            $html,
            "First panel 'id' must match its tab href.",
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockWebApplication();
    }

    protected function tearDown(): void
    {
        $this->destroyApplication();

        parent::tearDown();
    }

    /**
     * @param list<string> $flags
     */
    private static function makeHero(
        string $method = 'GET',
        string $url = 'http://example.test/',
        int $statusCode = 200,
        string $statusVariant = 'success',
        string $ip = '',
        string $time = '',
        string $durationMs = '',
        array $flags = [],
    ): RequestHero {
        return new RequestHero(
            method: $method,
            url: $url,
            statusCode: $statusCode,
            statusVariant: $statusVariant,
            ip: $ip,
            time: $time,
            durationMs: $durationMs,
            flags: $flags,
        );
    }
}
