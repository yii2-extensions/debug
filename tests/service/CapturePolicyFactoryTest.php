<?php

declare(strict_types=1);

namespace yii\debug\tests\service;

use InvalidArgumentException;
use PHPForge\Debug\Helper\SensitiveDataRedactor;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\Module;
use yii\debug\service\CapturePolicyFactory;
use yii\debug\tests\support\ModuleTestCase;

use function array_values;

/**
 * Unit tests for {@see CapturePolicyFactory} building the policy that redacts captured data.
 */
#[Group('service')]
final class CapturePolicyFactoryTest extends ModuleTestCase
{
    public function testCreateAppliesTheCollectorKeysOnTopOfTheGlobalRules(): void
    {
        $module = new Module('debug');

        $module->maxBodyBytes = 4;
        $module->sensitiveKeyPrefixes = ['internal_'];
        $module->sensitiveKeyPatterns = ['~(?:^|_)private(?:$|_)~i'];

        $policy = (new CapturePolicyFactory($module))->create(['queueSecret']);

        self::assertSame(
            [
                'DB_PASSWORD' => SensitiveDataRedactor::PLACEHOLDER,
                'internal_note' => SensitiveDataRedactor::PLACEHOLDER,
                'project_private_value' => SensitiveDataRedactor::PLACEHOLDER,
                'queueSecret' => SensitiveDataRedactor::PLACEHOLDER,
                'tokenizer' => 'safe-tokenizer',
            ],
            $policy->redact(
                [
                    'DB_PASSWORD' => 'database-secret',
                    'internal_note' => 'private-note',
                    'project_private_value' => 'pattern-secret',
                    'queueSecret' => 'queue-secret',
                    'tokenizer' => 'safe-tokenizer',
                ],
            ),
            'Exact, prefix, pattern, and collector-specific rules must all apply.',
        );
        self::assertSame(
            'abcd' . SensitiveDataRedactor::TRUNCATED,
            $policy->redactBody('abcdef', null)['raw'],
            'Body limit must reach the policy.',
        );
    }

    public function testCreateEnablesTheDefaultPatternsOnlyForAnUntouchedKeyList(): void
    {
        $module = new Module('debug');
        $factory = new CapturePolicyFactory($module);

        self::assertSame(
            [SensitiveDataRedactor::PLACEHOLDER],
            array_values($factory->create()->redact(['my.password.value' => 'x'])),
            'Untouched defaults must keep the shared patterns active.',
        );

        $module->sensitiveKeys = [
            ...SensitiveDataRedactor::DEFAULT_KEYS,
            'extra',
        ];

        self::assertSame(
            ['x'],
            array_values($factory->create()->redact(['my.password.value' => 'x'])),
            'A customized key list must not enable the patterns implicitly.',
        );
    }

    public function testCreateReadsTheModuleConfigurationAtCallTime(): void
    {
        $module = new Module('debug');
        $factory = new CapturePolicyFactory($module);

        $module->maxBodyBytes = 2;

        self::assertSame(
            'ab' . SensitiveDataRedactor::TRUNCATED,
            $factory->create()->redactBody('abcdef', null)['raw'],
            'Configuration set after construction must apply.',
        );
    }

    public function testThrowInvalidArgumentExceptionForAnEmptySensitiveKeyPrefix(): void
    {
        $module = new Module('debug');

        $module->sensitiveKeyPrefixes = [''];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Sensitive key prefixes must not be empty.',
        );

        (new CapturePolicyFactory($module))->create();
    }
}
