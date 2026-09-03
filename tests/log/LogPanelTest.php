<?php

declare(strict_types=1);

namespace yii\debug\tests\log;

use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Storage\HydrationException;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use Yii;
use yii\debug\panels\LogPanel;
use yii\debug\tests\provider\VisibilityProvider;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

use function html_entity_decode;
use function is_string;
use function parse_str;
use function parse_url;
use function preg_match;
use function preg_quote;

/**
 * Unit tests for {@see LogPanel} covering payload narrowing, toolbar items per level, the rendered detail and summary
 * views, and the typed row decoration with previous/next ids.
 *
 * {@see VisibilityProvider} for method contract data providers.
 */
#[Group('panel')]
#[Group('log')]
final class LogPanelTest extends TestCase
{
    public function testCapturePreservesTimestampsAndPreviousRowDelta(): void
    {
        $rows = LogSnapshot::capture(
            [
                ['first', Logger::LEVEL_INFO, 'application', 2.5, []],
                ['second', Logger::LEVEL_INFO, 'application', 4.0, []],
            ],
        )->entries();

        $first = $rows[0] ?? self::fail('Expected the first captured row.');
        $second = $rows[1] ?? self::fail('Expected the second captured row.');

        self::assertSame(
            2500.0,
            $first->time,
            'The tuple timestamp must be read from index three.',
        );
        self::assertSame(
            2500.0,
            $first->timeOfPrevious,
            'The first row must reference its own timestamp.',
        );
        self::assertSame(
            4000.0,
            $second->time,
            'The second tuple timestamp must remain intact.',
        );
        self::assertSame(
            2500.0,
            $second->timeOfPrevious,
            'The second row must reference the first timestamp.',
        );
        self::assertSame(
            1.5,
            $second->timeSincePrevious,
            'The elapsed time must compare adjacent rows.',
        );
    }

    /**
     * @param class-string $class
     * @param 'protected'|'public' $expected
     */
    #[DataProviderExternal(VisibilityProvider::class, 'logPanelContracts')]
    public function testExtensionMethodKeepsDeclaredVisibility(string $class, string $method, string $expected): void
    {
        self::assertMethodVisibility($class, $method, $expected);
    }

    public function testGetDetailRendersErrorAndWarningCountersWhenLevelsArePresent(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['oops', Logger::LEVEL_ERROR, 'application', 1.0, []],
                    ['careful', Logger::LEVEL_WARNING, 'application', 2.0, []],
                    ['hello', Logger::LEVEL_INFO, 'application', 3.0, []],
                ],
            ),
        );

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'errors',
            $html,
            'Error counter must surface.',
        );
        self::assertStringContainsString(
            'warnings',
            $html,
            'Warning counter must surface.',
        );
    }

    public function testGetDetailRendersWithCapturedMessages(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['hello', Logger::LEVEL_INFO, 'application', 0.0, []],
                ],
            ),
        );

        self::assertNotEmpty(
            $panel->getDetail(),
            'Detail view must produce markup.',
        );
    }

    public function testGetDetailSummaryLinksReplaceLogFiltersAndPreserveUnrelatedQueryState(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $panel->id = 'log';
        $panel->tag = 'log-tag';

        Yii::$app->getRequest()->setQueryParams(
            [
                'tag' => 'log-tag',
                'panel' => 'log',
                'Log' => [
                    'level' => (string) Logger::LEVEL_WARNING,
                    'category' => 'application',
                    'message' => 'needle',
                ],
                'sort' => '-message',
                'per-page' => '25',
                'page' => '3',
                'yii_debug_theme' => 'dark',
                'Debug' => ['statusCode' => '302'],
                'custom' => 'kept',
            ],
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['oops', Logger::LEVEL_ERROR, 'application', 1.0, []],
                    ['careful', Logger::LEVEL_WARNING, 'application', 2.0, []],
                    ['hello', Logger::LEVEL_INFO, 'application', 3.0, []],
                    ['details', Logger::LEVEL_TRACE, 'application', 4.0, []],
                ],
            ),
        );

        $html = $panel->getDetail();

        foreach (
            [
                'Show only error log messages' => (string) Logger::LEVEL_ERROR,
                'Show only warning log messages' => (string) Logger::LEVEL_WARNING,
                'Show only info log messages' => (string) Logger::LEVEL_INFO,
                'Show only trace log messages' => (string) Logger::LEVEL_TRACE,
            ] as $title => $level
        ) {
            $query = self::summaryLinkQuery($html, $title);

            self::assertSame(
                ['level' => $level],
                $query['Log'] ?? null,
                'Each summary link must replace the complete Log filter group with its severity.',
            );
            self::assertSame(
                '-message',
                $query['sort'] ?? null,
                'The sort parameter must be preserved.',
            );
            self::assertSame(
                '25',
                $query['per-page'] ?? null,
                'The page-size parameter must be preserved.',
            );
            self::assertSame(
                'dark',
                $query['yii_debug_theme'] ?? null,
                'The theme parameter must be preserved.',
            );
            self::assertSame(
                ['statusCode' => '302'],
                $query['Debug'] ?? null,
                'Unrelated filter groups must be preserved.',
            );
            self::assertSame(
                'kept',
                $query['custom'] ?? null,
                'Custom query state must be preserved.',
            );
        }

        foreach ([
            '1 errors; filter log messages by error level',
            '1 warnings; filter log messages by warning level',
            '1 info; filter log messages by info level',
            '1 trace; filter log messages by trace level',
        ] as $ariaLabel) {
            self::assertStringContainsString(
                'aria-label="' . $ariaLabel . '"',
                $html,
                'Severity shortcuts must retain their visible count and label in the accessible name.',
            );
        }
    }

    public function testGetMessagesReflectsTheLatestHydration(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['a', Logger::LEVEL_INFO, 'application', 0.0, []],
                ],
            ),
        );

        self::assertCount(
            1,
            $panel->getMessages(),
            'Single message must yield one row.',
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['a', Logger::LEVEL_INFO, 'application', 0.0, []],
                    ['b', Logger::LEVEL_INFO, 'application', 0.0, []],
                ],
            ),
        );

        self::assertCount(
            2,
            $panel->getMessages(),
            'Re-hydration must replace the previous rows.',
        );
    }

    public function testGetMessagesReturnsEmptyListBeforeHydration(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        self::assertSame(
            [],
            $panel->getMessages(),
            'An un-hydrated panel exposes no rows.',
        );
    }

    public function testGetModelsCachesAndDecoratesPrevNextIds(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['a', Logger::LEVEL_INFO, 'application', 1.0, []],
                    ['b', Logger::LEVEL_WARNING, 'application', 2.0, []],
                    ['c', Logger::LEVEL_ERROR, 'application', 3.0, []],
                ],
            ),
        );

        $rows = $panel->getMessages();

        self::assertSame(
            $rows,
            $panel->getMessages(),
            'Repeated reads must return the same rows.',
        );

        $row = $rows[1] ?? self::fail("Expected row id '2'.");

        self::assertSame(
            2,
            $row->id,
            "Middle row must carry id '2'.",
        );
        self::assertSame(
            1,
            $row->idOfPrevious,
            "Middle row must point back to id '1'.",
        );
        self::assertSame(
            3,
            $row->idOfNext,
            "Middle row must point forward to id '3'.",
        );
    }

    public function testGetModelsLastRowExposesNullAsNextId(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['a', Logger::LEVEL_INFO, 'application', 1.0, []],
                    ['b', Logger::LEVEL_INFO, 'application', 2.0, []],
                ],
            ),
        );

        $rows = $panel->getMessages();

        $last = $rows[1] ?? self::fail("Expected row id '2'.");

        self::assertNull(
            $last->idOfNext,
            "Last row must expose 'null' as the next id.",
        );
        self::assertSame(
            1,
            $last->idOfPrevious,
            "Last row must point back to id '1'.",
        );
    }

    public function testGetModelsScalesTimeToMilliseconds(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['msg', Logger::LEVEL_INFO, 'application', 2.5, []],
                ],
            ),
        );

        $row = $panel->getMessages()[0] ?? self::fail("Expected row id '1'.");

        self::assertEqualsWithDelta(
            2500.0,
            $row->time,
            1e-9,
            'Time must be scaled to milliseconds.',
        );
    }

    public function testGetNameAndIcon(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        self::assertSame(
            'Logs',
            $panel->getName(),
            "Display name must be 'Logs'.",
        );
        self::assertSame(
            'logs',
            $panel->getToolbarIcon(),
            "Icon key must be 'logs'.",
        );
    }

    public function testGetToolbarItemsEmitsCountChipOnly(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['a', Logger::LEVEL_INFO, 'application', 0.0, []],
                ],
            ),
        );

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Items must be a list.',
        );
        self::assertCount(
            1,
            $items,
            'No errors/warnings means only the count chip.',
        );

        $first = $items[0] ?? self::fail('Expected one item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            1,
            $first['value'] ?? null,
            'Count chip must match the message count.',
        );
    }

    public function testGetToolbarItemsEmitsDangerChipWhenErrorsPresent(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['err', Logger::LEVEL_ERROR, 'application', 0.0, []],
                    ['info', Logger::LEVEL_INFO, 'application', 0.0, []],
                ],
            ),
        );

        self::assertSame(
            [
                ['value' => 2],
                [
                    'label' => 'Errors',
                    'status' => 'danger',
                    'url' => $panel->getUrl(['Log[level]' => Logger::LEVEL_ERROR]),
                    'value' => 1,
                ],
            ],
            $this->invoke($panel, 'getToolbarItems'),
            'Error chip must report the captured count.',
        );
    }

    public function testGetToolbarItemsEmitsWarningChipWhenWarningsPresent(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['warn', Logger::LEVEL_WARNING, 'application', 0.0, []],
                ],
            ),
        );

        self::assertSame(
            [
                ['value' => 1],
                [
                    'label' => 'Warnings',
                    'status' => 'warning',
                    'url' => $panel->getUrl(['Log[level]' => Logger::LEVEL_WARNING]),
                    'value' => 1,
                ],
            ],
            $this->invoke($panel, 'getToolbarItems'),
            'Warning chip must report the captured count.',
        );
    }

    public function testGetToolbarItemsReturnsEmptyArrayWhenNoMessagesWereCaptured(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        self::assertSame(
            [],
            $this->invoke($panel, 'getToolbarItems'),
            'No captured log messages must skip the toolbar.',
        );
    }

    public function testThrowHydrationExceptionWhenMessagesAreNotAnArray(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '$.panels..entries': expected a required field.",
        );

        $panel->hydrate(['messages' => 'corrupt']);
    }

    /**
     * @return array<string, mixed>
     */
    private static function summaryLinkQuery(string $html, string $title): array
    {
        $matches = [];

        $result = preg_match(
            '/<a\\b(?=[^>]*\\btitle="' . preg_quote($title, '/') . '")(?=[^>]*\\bhref="([^"]+)")[^>]*>/',
            $html,
            $matches,
        );

        self::assertSame(
            1,
            $result,
            "Expected a summary link titled '{$title}'.",
        );

        $encodedUrl = $matches[1];

        $url = html_entity_decode($encodedUrl, ENT_QUOTES | ENT_HTML5);
        $queryString = parse_url($url, PHP_URL_QUERY);

        if (!is_string($queryString)) {
            self::fail("Expected the '{$title}' link to contain a query string.");
        }

        $parsedQuery = [];

        parse_str($queryString, $parsedQuery);

        $query = [];

        foreach ($parsedQuery as $name => $value) {
            if (is_string($name)) {
                $query[$name] = $value;
            }
        }

        return $query;
    }
}
