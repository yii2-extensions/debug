<?php

declare(strict_types=1);

namespace yii\debug\tests\mail;

use DateTimeImmutable;
use PHPForge\Debug\Panel\Mail\MailSnapshot;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use yii\debug\LogTarget;
use yii\debug\panels\MailPanel;
use yii\debug\tests\support\TestCase;
use yii\helpers\Url;

use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Unit tests for {@see MailPanel} covering payload narrowing, toolbar items (current vs cross-request), the
 * previous-request fallback, and the rendered detail/summary views.
 */
#[Group('panel')]
#[Group('mail')]
final class MailPanelTest extends TestCase
{
    public function testFindPreviousRequestUsesImmediateEntryAfterCurrentTag(): void
    {
        $panel = $this->makePanel(
            MailPanel::class,
        );

        $module = $panel->module ?? self::fail('Module must be wired.');

        $dataPath = sys_get_temp_dir() . '/debug-mail-order-' . uniqid();

        mkdir($dataPath, 0o777, true);

        $module->dataPath = $dataPath;

        foreach (
            [
                'older' => ['url' => 'https://example.com/older', 'mailCount' => 7],
                'previous' => ['url' => 'https://example.com/send-mail', 'mailCount' => 2],
                'current' => ['url' => 'https://example.com/current', 'mailCount' => 0],
                'newest' => ['url' => 'https://example.com/newest', 'mailCount' => 9],
            ] as $tag => $summary
        ) {
            $this->writeDebugSnapshot(
                $module,
                $tag,
                [],
                [
                    'method' => 'POST',
                    ...$summary,
                ],
            );
        }

        $panel->tag = 'current';

        self::assertSame(
            [
                'count' => 2,
                'method' => 'POST',
                'shortUrl' => '/send-mail',
                'url' => Url::toRoute(['/debug/view', 'panel' => $panel->id, 'tag' => 'previous']),
            ],
            $this->invoke($panel, 'findPreviousRequestWithMail'),
            'Previous request must be the immediate entry after current in the manifest, not the newest.',
        );

        $this->cleanupDataPath($dataPath);
    }

    public function testFindPreviousRequestUsesNewestEntryWhenCurrentTagIsAbsent(): void
    {
        $panel = $this->makePanel(
            MailPanel::class,
        );

        $module = $panel->module ?? self::fail('Module must be wired.');

        $dataPath = sys_get_temp_dir() . '/debug-mail-fallback-' . uniqid();
        mkdir($dataPath, 0o777, true);

        $module->dataPath = $dataPath;

        $this->writeDebugSnapshot(
            $module,
            'older',
            [],
            ['method' => 'GET', 'url' => 'https://example.com/older', 'mailCount' => 1],
        );
        $this->writeDebugSnapshot(
            $module,
            'newest',
            [],
            ['method' => 'PATCH', 'url' => 'https://example.com/newest', 'mailCount' => 3],
        );

        $panel->tag = 'missing';

        self::assertSame(
            [
                'count' => 3,
                'method' => 'PATCH',
                'shortUrl' => '/newest',
                'url' => Url::toRoute(['/debug/view', 'panel' => $panel->id, 'tag' => 'newest']),
            ],
            $this->invoke($panel, 'findPreviousRequestWithMail'),
            'Previous request must be the newest entry in the manifest when current is absent.',
        );

        $this->cleanupDataPath($dataPath);
    }

    public function testFindPreviousRequestWithMailReturnsNullWhenLoadManifestThrows(): void
    {
        $panel = $this->makePanel(
            MailPanel::class,
        );

        $module = $panel->module ?? self::fail('Module must be wired.');

        $throwingLogTarget = new class ($module) extends LogTarget {
            public function loadManifest(): array
            {
                throw new RuntimeException('boom');
            }
        };

        $this->setInaccessibleProperty(
            $module,
            'logTarget',
            $throwingLogTarget,
        );

        self::assertNull(
            $this->invoke(
                $panel,
                'findPreviousRequestWithMail',
            ),
            "Throwable from log target must collapse to 'null'.",
        );
    }

    public function testFindPreviousRequestWithMailReturnsNullWhenLogTargetIsMissing(): void
    {
        $panel = $this->makePanel(
            MailPanel::class,
        );

        $module = $panel->module ?? self::fail('Module must be wired.');

        $this->setInaccessibleProperty(
            $module,
            'logTarget',
            '',
        );

        self::assertNull(
            $this->invoke(
                $panel,
                'findPreviousRequestWithMail',
            ),
            "Missing log target must collapse to 'null'.",
        );
    }

    public function testFindPreviousRequestWithMailReturnsNullWhenManifestIsEmpty(): void
    {
        $panel = $this->makePanel(
            MailPanel::class,
        );

        $module = $panel->module ?? self::fail('Module must be wired.');

        $dataPath = sys_get_temp_dir() . '/debug-mail-empty-' . uniqid();
        mkdir($dataPath, 0o777, true);

        $module->dataPath = $dataPath;

        self::assertNull(
            $this->invoke(
                $panel,
                'findPreviousRequestWithMail',
            ),
            "Empty manifest must collapse to 'null'.",
        );

        $this->cleanupDataPath($dataPath);
    }

    public function testFindPreviousRequestWithMailReturnsNullWhenModuleIsMissing(): void
    {
        $panel = $this->makePanel(
            MailPanel::class,
        );

        $panel->module = null;

        self::assertNull(
            $this->invoke(
                $panel,
                'findPreviousRequestWithMail',
            ),
            "Missing module must collapse to 'null'.",
        );
    }

    public function testFindPreviousRequestWithMailReturnsNullWhenOnlyTagInManifestIsCurrent(): void
    {
        $panel = $this->makePanel(
            MailPanel::class,
        );

        $module = $panel->module ?? self::fail('Module must be wired.');

        $dataPath = sys_get_temp_dir() . '/debug-mail-self-' . uniqid();
        mkdir($dataPath, 0o777, true);

        $module->dataPath = $dataPath;

        $tag = 'only-tag';

        $this->writeDebugSnapshot(
            $module,
            $tag,
            [],
            ['method' => 'GET', 'url' => '/', 'mailCount' => 0],
        );

        $panel->tag = $tag;

        self::assertNull(
            $this->invoke(
                $panel,
                'findPreviousRequestWithMail',
            ),
            "Single-tag manifest matching current must collapse to 'null'.",
        );

        $this->cleanupDataPath($dataPath);
    }

    public function testGetDetailRendersEmptyStateWhenNoMessagesCaptured(): void
    {
        $panel = $this->makePanel(
            MailPanel::class,
        );

        $this->hydratePanel(
            $panel,
            MailSnapshot::capture([]),
        );

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'No emails sent in this request',
            $html,
            'Empty mail panel must render the no-messages hint.',
        );
    }

    public function testGetDetailRendersWithCapturedMessages(): void
    {
        $panel = $this->makePanel(
            MailPanel::class,
        );

        $this->hydratePanel(
            $panel,
            MailSnapshot::capture(
                [
                    [
                        'from' => 'a@x.com',
                        'to' => 'b@x.com',
                        'subject' => 'Hello',
                        'time' => new DateTimeImmutable('2026-01-01'),
                    ],
                ],
            ),
        );

        $detail = $panel->getDetail();

        self::assertNotEmpty(
            $detail,
            'Detail view must produce markup.',
        );
        self::assertStringContainsString(
            'yii-debug-grid-summary',
            $detail,
            'Detail must open with the shared summary strip.',
        );
    }

    public function testGetNameAndIcon(): void
    {
        $panel = $this->makePanel(
            MailPanel::class,
        );

        self::assertSame(
            'Mail',
            $panel->getName(),
            "Display name must be 'Mail'.",
        );
        self::assertSame(
            'mail',
            $panel->getToolbarIcon(),
            "Icon key must be 'mail'.",
        );
    }

    public function testGetToolbarItemsEmitsCountChipWhenMessagesPresent(): void
    {
        $panel = $this->makePanel(
            MailPanel::class,
        );

        $this->hydratePanel(
            $panel,
            MailSnapshot::capture(
                [
                    ['subject' => 'one'],
                    ['subject' => 'two'],
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

        $first = $items[0] ?? self::fail('Expected one item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            2,
            $first['value'] ?? null,
            'Chip value must match the message count.',
        );
    }

    public function testGetToolbarItemsEmitsCrossRequestChipWhenCurrentTagHasSuccessorInManifest(): void
    {
        $panel = $this->makePanel(
            MailPanel::class,
        );

        $module = $panel->module ?? self::fail('Module must be wired.');

        $dataPath = sys_get_temp_dir() . '/debug-mail-test-' . uniqid();
        mkdir($dataPath, 0o777, true);

        $module->dataPath = $dataPath;

        $currentTag = 'current-tag';
        $previousTag = 'previous-tag';

        // 'loadManifest()' reverses the on-disk order; writing 'previous' first then 'current' produces a load-time
        // manifest of [current, previous] so the loop hits the `$found` branch on iteration 2.
        $this->writeDebugSnapshot(
            $module,
            $previousTag,
            [],
            ['method' => 'POST', 'url' => 'https://example.com/send-mail', 'mailCount' => 1],
        );
        $this->writeDebugSnapshot(
            $module,
            $currentTag,
            [],
            ['method' => 'GET', 'url' => 'https://example.com/current', 'mailCount' => 0],
        );

        $panel->tag = $currentTag;

        $this->hydratePanel(
            $panel,
            MailSnapshot::capture([]),
        );

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Items must be a list.',
        );

        $first = $items[0] ?? self::fail('Expected one cross-request chip.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            'cross-request',
            $first['status'] ?? null,
            'Status must be cross-request when the manifest has a tag after current.',
        );

        $this->cleanupDataPath($dataPath);
    }

    public function testGetToolbarItemsEmitsCrossRequestChipWhenPreviousRequestHasMail(): void
    {
        $panel = $this->makePanel(
            MailPanel::class,
        );

        $module = $panel->module ?? self::fail('Module must be wired.');

        $dataPath = sys_get_temp_dir() . '/debug-mail-test-' . uniqid();
        mkdir($dataPath, 0o777, true);

        $module->dataPath = $dataPath;

        $previousTag = 'previous-tag';

        $this->writeDebugSnapshot(
            $module,
            $previousTag,
            [],
            [
                'method' => 'POST',
                'url' => 'https://example.com/send-mail',
                'mailCount' => 1,
            ],
        );

        $panel->tag = 'current-tag';

        $this->hydratePanel(
            $panel,
            MailSnapshot::capture([]),
        );

        $items = $this->invoke(
            $panel,
            'getToolbarItems'
        );

        self::assertIsArray(
            $items,
            'Items must be a list.',
        );

        $first = $items[0] ?? self::fail('Expected one cross-request chip.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            'cross-request',
            $first['status'] ?? null,
            "Status must be 'cross-request'."
        );
        self::assertSame(
            1,
            $first['value'] ?? null,
            'Chip value must count the previous-request messages.'
        );

        $title = $first['title'] ?? null;

        self::assertIsString(
            $title,
            'Cross-request chip must carry a title.',
        );
        self::assertStringContainsString(
            '/send-mail',
            $title,
            'Title must include the previous request short URL.',
        );

        $this->cleanupDataPath($dataPath);
    }

    public function testGetToolbarItemsEmitsWarningChipWhenSnapshotIsMissing(): void
    {
        $panel = $this->makePanel(
            MailPanel::class,
        );

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Items must be a list.',
        );

        $first = $items[0] ?? self::fail('Expected one item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            'warning',
            $first['status'] ?? null,
            "Corrupt data must yield a 'warning' status.",
        );
        self::assertSame(
            '!',
            $first['value'] ?? null,
            "Corrupt data must surface a '!' chip.",
        );
    }

    public function testGetToolbarItemsReturnsEmptyArrayWhenNoCurrentOrPreviousMail(): void
    {
        $panel = $this->makePanel(
            MailPanel::class,
        );

        $this->hydratePanel(
            $panel,
            MailSnapshot::capture([]),
        );

        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'getToolbarItems',
            ),
            'No data and no previous request means no chip.',
        );
    }

    private function cleanupDataPath(string $dataPath): void
    {
        $files = glob("{$dataPath}/*");

        foreach (is_array($files) ? $files : [] as $file) {
            @unlink($file);
        }

        @rmdir($dataPath);
    }
}
