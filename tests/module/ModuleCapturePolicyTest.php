<?php

declare(strict_types=1);

namespace yii\debug\tests\module;

use InvalidArgumentException;
use PHPForge\Debug\Helper\SensitiveDataRedactor;
use PHPUnit\Framework\Attributes\Group;
use yii\base\InvalidConfigException;
use yii\debug\Module;
use yii\debug\tests\support\ModuleTestCase;

/**
 * Unit tests for {@see Module} covering `createCapturePolicy` global and collector-specific rules, body limits,
 * default-pattern selection, and invalid policy configuration with its chained cause.
 */
#[Group('module')]
final class ModuleCapturePolicyTest extends ModuleTestCase
{
    public function testCreateCapturePolicyCombinesGlobalRulesWithCollectorKeysAndBodyLimit(): void
    {
        $module = new Module('debug');

        $module->maxBodyBytes = 4;
        $module->sensitiveKeyPrefixes = ['internal_'];
        $module->sensitiveKeyPatterns = ['~(?:^|_)private(?:$|_)~i'];

        $policy = $module->createCapturePolicy(['queueSecret']);
        $redacted = $policy->redact(
            [
                'DB_PASSWORD' => 'database-secret',
                'internal_note' => 'private-note',
                'project_private_value' => 'pattern-secret',
                'queueSecret' => 'queue-secret',
                'tokenizer' => 'safe-tokenizer',
            ],
        );

        self::assertSame(
            [
                'DB_PASSWORD' => SensitiveDataRedactor::PLACEHOLDER,
                'internal_note' => SensitiveDataRedactor::PLACEHOLDER,
                'project_private_value' => SensitiveDataRedactor::PLACEHOLDER,
                'queueSecret' => SensitiveDataRedactor::PLACEHOLDER,
                'tokenizer' => 'safe-tokenizer',
            ],
            $redacted,
            'Module policy must combine global exact, prefix, pattern, and collector-specific rules.',
        );
        self::assertSame(
            'abcd' . SensitiveDataRedactor::TRUNCATED,
            $policy->redactBody('abcdef', null)['raw'],
            'Module maxBodyBytes must configure the shared capture policy.',
        );
    }

    public function testCreateCapturePolicyEnablesDefaultPatternsOnlyForUntouchedDefaults(): void
    {
        $module = new Module('debug');

        $withDefaults = $module->createCapturePolicy(['queueSecret']);

        self::assertSame(
            [SensitiveDataRedactor::PLACEHOLDER],
            array_values($withDefaults->redact(['my.password.value' => 'x'])),
            'Default patterns must engage when the exact-key list is untouched.',
        );

        $noCollectorKeys = $module->createCapturePolicy();

        self::assertSame(
            [SensitiveDataRedactor::PLACEHOLDER],
            array_values($noCollectorKeys->redact(['my.password.value' => 'x'])),
            'Untouched defaults must keep the Debug Core patterns active.',
        );

        $module->sensitiveKeys = [
            ...SensitiveDataRedactor::DEFAULT_KEYS,
            'extra',
        ];

        $customized = $module->createCapturePolicy(['queueSecret']);

        self::assertSame(
            ['x'],
            array_values($customized->redact(['my.password.value' => 'x'])),
            'Customized exact keys must not enable the default patterns implicitly.',
        );
    }

    public function testInitRejectsInvalidCapturePolicyConfiguration(): void
    {
        try {
            new Module(
                'debug',
                null,
                ['sensitiveKeyPrefixes' => ['']],
            );

            self::fail(
                'Invalid capture policy configuration must be rejected.',
            );
        } catch (InvalidConfigException $exception) {
            self::assertSame(
                'Sensitive key prefixes must not be empty.',
                $exception->getMessage(),
                'Cause message must surface unchanged.',
            );
            self::assertSame(
                0,
                $exception->getCode(),
                "Code must stay at '0'.",
            );
            self::assertInstanceOf(
                InvalidArgumentException::class,
                $exception->getPrevious(),
                'Original cause must be chained.',
            );
        }
    }
}
