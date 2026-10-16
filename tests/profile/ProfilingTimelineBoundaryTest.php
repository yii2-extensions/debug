<?php

declare(strict_types=1);

namespace yii\debug\tests\profile;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use PHPUnit\Framework\Attributes\{Group, TestWith};
use Yii;
use yii\debug\panels\ProfilingPanel;
use yii\debug\tests\support\stub\CapturingView;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

/**
 * Tests rendered timeline units, unavailable metrics, and adapter-owned filter state.
 */
#[Group('profile')]
final class ProfilingTimelineBoundaryTest extends TestCase
{
    public function testCustomDetailViewReceivesAnUnavailableTimelineAfterFilteringEverySpan(): void
    {
        $panel = $this->panel(100.0, 0.2, 1048576);

        Yii::$app->set('view', CapturingView::class);
        Yii::$app->request->setQueryParams(['Profile' => ['category' => 'missing-category']]);

        self::assertSame(
            'rendered',
            $panel->getDetail(),
            'A custom detail view must still receive filtered capture state.',
        );

        $view = Yii::$app->getView();

        self::assertInstanceOf(
            CapturingView::class,
            $view,
            'The configured custom view must receive the panel data.',
        );

        $timeline = $view->renderParams['timeline'] ?? null;

        self::assertIsString(
            $timeline,
            'The timeline supplied to custom views must be rendered markup.',
        );
        self::assertStringContainsString(
            'The filtered spans cannot be positioned',
            $timeline,
            'Custom views must receive the unavailable state when filtering removes every span.',
        );
    }

    public function testFilterFormKeepsSortAndPagingButOmitsAnEmptyTheme(): void
    {
        $panel = $this->panel(100.0, 0.2, 1048576);

        Yii::$app->request->setQueryParams(['sort' => '-duration', 'per-page' => '17', 'yii_debug_theme' => '']);

        $detail = $panel->getDetail();

        self::assertNotSame(
            '',
            $detail,
            'The profiling filter form must render.',
        );

        $dom = new DOMDocument();

        $dom->loadHTML($detail, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new DOMXPath($dom);

        self::assertSame(
            '-duration',
            $xpath->evaluate('string(//input[@name="sort"]/@value)'),
            'Submitting filters must retain the current sort.',
        );
        self::assertSame(
            '17',
            $xpath->evaluate('string(//input[@name="per-page"]/@value)'),
            'Submitting filters must retain paging preferences.',
        );
        self::assertSame(
            0.0,
            $xpath->evaluate('count(//input[@name="yii_debug_theme"])'),
            'An empty theme must not become a persistent override.',
        );
    }

    #[TestWith([0.0, 0.2, 1048576])]
    #[TestWith([100.0, 0.0, 1048576])]
    #[TestWith([100.0, 0.2, 0])]
    public function testOneUnavailableMetricSuppressesTheTimeline(float $start, float $duration, int $memory): void
    {
        $detail = $this->panel($start, $duration, $memory)->getDetail();

        self::assertStringContainsString(
            'Timeline unavailable',
            $detail,
            'Each missing metric must produce an explicit unavailable state.',
        );
        self::assertStringNotContainsString(
            'The filtered spans cannot be positioned',
            $detail,
            'Missing capture metrics must not be diagnosed as a filter problem.',
        );
        self::assertStringNotContainsString(
            'class="yii-debug-tl-bar"',
            $detail,
            'Unavailable geometry must not render misleading positioned spans.',
        );
    }

    public function testPublicProcessingTimeReflectsHydratedRequestSeconds(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        self::assertNull(
            $panel->getProcessingTime(),
            'Processing time must be unavailable before hydration.',
        );
        self::assertSame(
            0.2,
            $this->panel(100.0, 0.2, 1048576)->getProcessingTime(),
            'Consumers must read request processing time in seconds.',
        );
    }

    #[TestWith([0.18])]
    #[TestWith([0.22])]
    public function testTimelinePositionsUseRequestSecondsAndSpanMilliseconds(float $duration): void
    {
        $panel = $this->panel(100.0, $duration, 1048576);

        $detail = $panel->getDetail();

        self::assertNotSame(
            '',
            $detail,
            'The profiling panel must render its chart.',
        );

        $dom = new DOMDocument();

        $dom->loadHTML($detail, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new DOMXPath($dom);

        $style = $xpath->evaluate('string(//div[@class="yii-debug-tl-bar"]/@style)');

        self::assertIsString(
            $style,
            'The timeline bar must expose CSS geometry.',
        );
        self::assertMatchesRegularExpression(
            '/left:\s*10(?:\.0+)?%;/',
            $style,
            'A span starting one tenth into the request must start at ten percent.',
        );
        self::assertMatchesRegularExpression(
            '/width:\s*30(?:\.0+)?%;/',
            $style,
            'A span lasting three tenths of the request must occupy thirty percent.',
        );
    }

    /**
     * @param list<string> $labels Visible tick labels in request-relative milliseconds.
     * @param list<int> $positions Tick positions as percentages of the chart width.
     */
    #[TestWith([0.2, ['0 ms', '50 ms', '100 ms', '150 ms'], [0, 25, 50, 75]])]
    #[TestWith([0.1, ['0 ms', '50 ms'], [0, 50]])]
    public function testTimelineRulerKeepsItsVisibleScaleAtAdaptiveStepBoundaries(float $duration, array $labels, array $positions): void
    {
        $detail = $this->panel(100.0, $duration, 1048576)->getDetail();

        self::assertNotSame(
            '',
            $detail,
            'A captured request must render the profiling timeline.',
        );

        $dom = new DOMDocument();

        $dom->loadHTML($detail, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        $ticks = (new DOMXPath($dom))->query('//header[@class="yii-debug-tl-axis"]/span');

        self::assertNotFalse(
            $ticks,
            'The timeline ruler must expose its visible ticks.',
        );

        $actualLabels = [];

        foreach ($ticks as $index => $tick) {
            self::assertInstanceOf(
                DOMElement::class,
                $tick,
                'Each ruler tick must be a positioned HTML element.',
            );

            $actualLabels[] = $tick->textContent;
            $position = $positions[$index] ?? self::fail('The ruler emitted an unexpected tick.');

            self::assertMatchesRegularExpression(
                "/left:\\s*{$position}(?:\\.0+)?%;/",
                $tick->getAttribute('style'),
                'Each label must stay aligned with its elapsed request time.',
            );
        }

        self::assertSame(
            $labels,
            $actualLabels,
            'Adaptive ruler steps must preserve the visible request scale at rounding boundaries.',
        );
    }

    private function panel(float $start, float $duration, int $memory): ProfilingPanel
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $panel->id = 'profiling';
        $panel->tag = 'synthetic-profile';
        $panel->setRequestSummary($this->requestSummary(overrides: ['time' => $start]));

        $begin = 100.0 + $duration / 10;
        $end = $begin + $duration * 0.3;

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(
                $memory,
                $duration,
                [
                    ['synthetic-span', Logger::LEVEL_PROFILE_BEGIN, 'application', $begin, [], 1000],
                    ['synthetic-span', Logger::LEVEL_PROFILE_END, 'application', $end, [], 2000],
                ],
            ),
        );

        return $panel;
    }
}
