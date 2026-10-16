<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use PHPForge\Debug\Helper\SensitiveDataRedactor;
use PHPForge\Debug\Panel\User\UserSnapshot;
use PHPForge\Debug\Storage\DebugSnapshot;
use PHPUnit\Framework\Attributes\{Group, TestWith};
use Yii;
use yii\debug\collectors\UserCollector;
use yii\debug\{LogTarget, Module};
use yii\debug\tests\support\stub\StructuredIdentity;
use yii\debug\tests\support\TestCase;
use yii\helpers\FileHelper;
use yii\web\User;

use function array_column;
use function file_get_contents;
use function sys_get_temp_dir;
use function uniqid;

/**
 * Verifies identity redaction at the persisted JSON boundary without mutating application data.
 */
#[Group('collector')]
#[Group('user')]
final class IdentityCaptureRedactionTest extends TestCase
{
    #[TestWith([false])]
    #[TestWith([true])]
    public function testPersistedIdentityRedactsStructuredValuesBeforeFormatting(bool $object): void
    {
        $this->mockWebApplication(
            ['components' => ['user' => ['class' => User::class, 'identityClass' => StructuredIdentity::class]]],
        );

        $profile = [
            'password' => 'SYNTHETIC_NESTED_PASSWORD',
            'child' => ['access_token' => 'SYNTHETIC_NESTED_TOKEN'],
            'pin' => 'SYNTHETIC_CUSTOM_KEY',
            'private_note' => 'SYNTHETIC_CUSTOM_PREFIX',
            'credential7' => 'SYNTHETIC_CUSTOM_PATTERN',
            'name' => 'Synthetic Alice',
            'passwordless' => true,
        ];

        $identity = new StructuredIdentity();

        $identity->profile = $object ? (object) $profile : $profile;

        Yii::$app->user->setIdentity($identity);
        Yii::$app->request->setUrl('/synthetic');

        $module = new Module(
            'debug',
            null,
            [
                'dataPath' => sys_get_temp_dir() . '/debug-identity-redaction-' . uniqid(),
                'sensitiveKeys' => [...SensitiveDataRedactor::DEFAULT_KEYS, 'pin'],
                'sensitiveKeyPrefixes' => ['private_'],
                'sensitiveKeyPatterns' => [...SensitiveDataRedactor::DEFAULT_PATTERNS, '~^credential[0-9]+$~'],
            ],
        );

        $target = new LogTarget($module);

        $module->logTarget = $target;

        $collector = $module->getCollectorCoordinator()->collector('user');

        self::assertInstanceOf(
            UserCollector::class,
            $collector,
            'The user collector must be registered.',
        );

        $collector->startup();

        try {
            $target->export();

            $json = file_get_contents("{$module->dataPath}/{$target->tag}.json");

            self::assertIsString(
                $json,
                'Identity data must be written to the real snapshot file.',
            );

            foreach ([
                $identity->password,
                'SYNTHETIC_NESTED_PASSWORD',
                'SYNTHETIC_NESTED_TOKEN',
                'SYNTHETIC_CUSTOM_KEY',
                'SYNTHETIC_CUSTOM_PREFIX',
                'SYNTHETIC_CUSTOM_PATTERN',
            ] as $secret) {
                self::assertStringNotContainsString(
                    $secret,
                    $json,
                    'Persisted identity data must not contain secrets.',
                );
            }

            $snapshot = $target->loadSnapshot($target->tag);

            self::assertInstanceOf(
                DebugSnapshot::class,
                $snapshot,
                'The persisted snapshot must remain readable.',
            );

            $payload = UserSnapshot::fromArray($snapshot->panels['user'] ?? [], '$.panels.user')->data();
            $saved = $payload['identity'] ?? null;

            self::assertIsArray(
                $saved,
                'The identity attribute map must survive persistence.',
            );
            self::assertSame(
                SensitiveDataRedactor::PLACEHOLDER,
                $saved['password'] ?? null,
                'Top-level markers must stay unquoted.',
            );
            self::assertIsString(
                $saved['profile'] ?? null,
                'Structured identity data must remain displayable.',
            );
            self::assertStringContainsString(
                'Synthetic Alice',
                $saved['profile'],
                'Safe nested values must remain visible.',
            );
            self::assertStringContainsString(
                "'passwordless' => true",
                $saved['profile'],
                'Safe feature flags must remain visible.',
            );

            $attributes = $payload['attributes'] ?? null;

            self::assertIsArray(
                $attributes,
                'Model attribute metadata must remain available.',
            );
            self::assertContains(
                'Profile details',
                array_column($attributes, 'label'),
                'Model labels must survive redaction.',
            );
            self::assertSame(
                $profile,
                (array) $identity->profile,
                'Capturing must preserve the live identity values.',
            );
        } finally {
            $collector->shutdown();

            FileHelper::removeDirectory($module->dataPath);
        }
    }
}
