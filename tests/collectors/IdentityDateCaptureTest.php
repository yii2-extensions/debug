<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use PHPForge\Debug\Panel\User\UserSnapshot;
use PHPUnit\Framework\Attributes\{Group, TestWith};
use Yii;
use yii\debug\{LogTarget, Module, collectors\UserCollector};
use yii\debug\tests\support\stub\{IdentityDateTime, StructuredIdentity};
use yii\debug\tests\support\TestCase;
use yii\helpers\FileHelper;
use yii\web\User;

use function file_get_contents;
use function sys_get_temp_dir;
use function uniqid;

/**
 * Verifies readable date values and bounded identity normalization at the persisted capture boundary.
 */
#[Group('collector')]
#[Group('user')]
final class IdentityDateCaptureTest extends TestCase
{
    public function testNumericPublicPropertiesRemainReadable(): void
    {
        $identity = new StructuredIdentity();

        $identity->profile = (object) [0 => 'Synthetic numbered value'];

        [$rendered] = $this->persist($identity);

        self::assertStringContainsString(
            'Synthetic numbered value',
            $rendered,
            'Public JSON-style numeric property names must survive capture.',
        );
    }

    public function testOversizedIdentityCollectionIsTruncatedAfterKeepingEarlierDates(): void
    {
        $identity = new StructuredIdentity();

        $identity->profile = [
            'created_at' => new DateTimeImmutable('2026-01-02T03:04:05+00:00'),
            'entries' => array_fill(0, 10001, 'Synthetic safe entry'),
        ];

        [$rendered] = $this->persist($identity);

        self::assertStringContainsString(
            '2026-01-02T03:04:05.000000+00:00 +00:00',
            $rendered,
            'The entry budget must retain earlier safe dates.',
        );
        self::assertSame(
            1,
            substr_count($rendered, '[truncated]'),
            'Truncation must collapse the omitted tail into one marker.',
        );
        self::assertCount(
            10001,
            $identity->profile['entries'],
            'Capture must preserve the live oversized collection.',
        );
    }

    public function testPersistedIdentityHandlesCyclesWithoutLosingSiblingDates(): void
    {
        $profile = (object) [
            'created_at' => new DateTimeImmutable('2026-01-02T03:04:05+00:00'),
            'password' => 'SYNTHETIC_CYCLE_PASSWORD',
        ];

        $profile->self = $profile;

        $identity = new StructuredIdentity();

        $identity->profile = $profile;

        [$rendered, $json] = $this->persist($identity);

        self::assertStringContainsString(
            '2026-01-02T03:04:05.000000+00:00 +00:00',
            $rendered,
            'Cycles must not hide safe sibling dates.',
        );
        self::assertStringNotContainsString(
            'SYNTHETIC_CYCLE_PASSWORD',
            $json,
            'Repeated sensitive values must stay redacted.',
        );
        self::assertStringContainsString(
            '[truncated]',
            $rendered,
            'Cycles must end at a visible depth boundary.',
        );
        self::assertSame(
            10,
            substr_count($rendered, '2026-01-02T03:04:05.000000+00:00 +00:00'),
            'Every safe date within the depth boundary must remain visible.',
        );
        self::assertSame(
            $profile,
            $identity->profile->self,
            'Capture must preserve cyclic live references.',
        );
    }

    #[TestWith([9996, 9996, 0])]
    #[TestWith([9997, 9997, 0])]
    #[TestWith([9998, 9997, 1])]
    public function testPersistedIdentityKeepsEveryEntryWithinTheBudget(
        int $entryCount,
        int $retainedCount,
        int $truncationCount,
    ): void {
        $identity = new StructuredIdentity();

        // The id, password and profile attributes consume three of the 10,000 inspected entries.
        $profile = [];

        for ($entry = 0; $entry < $entryCount; $entry++) {
            $profile['entry_' . $entry] = 'Synthetic budget entry';
        }

        $identity->profile = $profile;

        [$rendered] = $this->persist($identity);

        self::assertSame(
            $retainedCount,
            substr_count($rendered, 'Synthetic budget entry'),
            'Persisted identity values must fill the entry budget exactly.',
        );
        self::assertSame(
            $truncationCount,
            substr_count($rendered, '[truncated]'),
            'Only entries beyond the budget must produce a truncation marker.',
        );
        self::assertCount(
            $entryCount,
            $identity->profile,
            'Capture must preserve the live collection at every budget boundary.',
        );
    }

    /**
     * @param class-string<DateTimeInterface> $class Native date implementation to capture.
     */
    #[TestWith([DateTime::class, 'direct'])]
    #[TestWith([DateTimeImmutable::class, 'direct'])]
    #[TestWith([IdentityDateTime::class, 'direct'])]
    #[TestWith([DateTime::class, 'array'])]
    #[TestWith([DateTimeImmutable::class, 'array'])]
    #[TestWith([IdentityDateTime::class, 'array'])]
    #[TestWith([DateTime::class, 'object'])]
    #[TestWith([DateTimeImmutable::class, 'object'])]
    #[TestWith([IdentityDateTime::class, 'object'])]
    public function testPersistedIdentityPreservesDatePrecisionAndTimezone(string $class, string $container): void
    {
        $date = new $class('2026-07-08 14:15:16.123456', new DateTimeZone('America/St_Johns'));

        $values = [
            'created_at' => $date,
            'password' => new DateTimeImmutable('1999-01-02T03:04:05+00:00'),
        ];

        $profile = match ($container) {
            'array' => $values,
            'object' => (object) $values,
            default => $date,
        };
        $identity = new StructuredIdentity();

        $identity->profile = $profile;

        [$rendered, $json] = $this->persist($identity);

        self::assertStringContainsString(
            '2026-07-08T14:15:16.123456-02:30 America/St_Johns',
            $rendered,
            'Dates must retain microseconds, offset, and timezone name.',
        );
        self::assertStringNotContainsString(
            '1999-01-02',
            $json,
            'Date values under sensitive keys must stay redacted.',
        );
        self::assertStringNotContainsString(
            'SYNTHETIC_DATE_PASSWORD',
            $json,
            'Date subclass properties must not be dumped.',
        );
        self::assertSame(
            $profile,
            $identity->profile,
            'Capture must preserve live date values and containers.',
        );
        self::assertSame(
            '2026-07-08T14:15:16.123456-02:30',
            DateTimeImmutable::createFromInterface($date)->format('Y-m-d\TH:i:s.uP'),
            'Capture must preserve the original native timestamp.',
        );
    }

    public function testRelatedObjectsDoNotExposeProtectedState(): void
    {
        $identity = new StructuredIdentity();

        $identity->profile = new class extends UserCollector {
            public string $name = 'Synthetic public value';
            protected string $internalMemo = 'SYNTHETIC_PROTECTED_IDENTITY_VALUE';
        };

        [$rendered, $json] = $this->persist($identity);

        self::assertStringContainsString(
            'Synthetic public value',
            $rendered,
            'Public values of related objects must remain inspectable.',
        );
        self::assertStringNotContainsString(
            'SYNTHETIC_PROTECTED_IDENTITY_VALUE',
            $json,
            'Collector class scope must not expose protected application state.',
        );
    }

    public function testSensitiveBranchesDoNotConsumeTheDateNormalizationBudget(): void
    {
        $identity = new StructuredIdentity();

        $identity->profile = [
            'password' => array_fill(0, 10001, 'SYNTHETIC_LARGE_SECRET'),
            'created_at' => new DateTimeImmutable('2026-01-02T03:04:05+00:00'),
        ];

        [$rendered, $json] = $this->persist($identity);

        self::assertStringContainsString(
            '2026-01-02T03:04:05.000000+00:00 +00:00',
            $rendered,
            'Redacted branches must not exhaust safe-value normalization.',
        );
        self::assertStringNotContainsString(
            'SYNTHETIC_LARGE_SECRET',
            $json,
            'Large sensitive branches must never reach storage.',
        );
    }

    /**
     * @return array{string, string} Persisted profile display and raw JSON.
     */
    private function persist(StructuredIdentity $identity): array
    {
        $this->mockWebApplication(
            [
                'components' => ['user' => ['class' => User::class, 'identityClass' => StructuredIdentity::class]],
            ],
        );

        Yii::$app->user->setIdentity($identity);
        Yii::$app->request->setUrl('/synthetic-date');

        $module = new Module('debug', null, ['dataPath' => sys_get_temp_dir() . '/debug-date-' . uniqid()]);
        $target = new LogTarget($module);

        $module->logTarget = $target;

        $collector = $module->getCollectorCoordinator()->collector('user');

        self::assertNotNull(
            $collector,
            'The identity collector must be registered.',
        );

        $collector->startup();

        try {
            $target->export();

            $json = file_get_contents("{$module->dataPath}/{$target->tag}.json");

            self::assertIsString(
                $json,
                'The snapshot must be written as JSON.',
            );

            $snapshot = $target->loadSnapshot($target->tag);

            self::assertNotNull(
                $snapshot,
                'The persisted snapshot must remain readable.',
            );

            $payload = UserSnapshot::fromArray($snapshot->panels['user'] ?? [], '$.panels.user')->data();
            $saved = $payload['identity'] ?? null;

            self::assertIsArray(
                $saved,
                'The identity attribute map must survive persistence.',
            );

            $rendered = $saved['profile'] ?? null;

            self::assertIsString(
                $rendered,
                'The profile must remain a display string.',
            );

            return [$rendered, $json];
        } finally {
            $collector->shutdown();

            FileHelper::removeDirectory($module->dataPath);
        }
    }
}
