<?php

declare(strict_types=1);

namespace yii\debug\tests\helpers;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\helpers\Vocabulary;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

/**
 * Unit tests for {@see Vocabulary} mapping Yii2 logger levels to semantic suffixes.
 *
 * @since 0.2
 */
#[Group('helpers')]
#[Group('vocabulary')]
final class VocabularyTest extends TestCase
{
    public function testLogLevelMapsLoggerLevelsToVocabularySuffixes(): void
    {
        $mappings = [
            Logger::LEVEL_ERROR => 'error',
            Logger::LEVEL_WARNING => 'warning',
            Logger::LEVEL_INFO => 'info',
            Logger::LEVEL_TRACE => 'trace',
            Logger::LEVEL_PROFILE => 'profile',
            Logger::LEVEL_PROFILE_BEGIN => 'profile',
            Logger::LEVEL_PROFILE_END => 'profile',
            0 => 'other',
            0x999 => 'other',
        ];

        foreach ($mappings as $level => $expected) {
            self::assertSame(
                $expected,
                Vocabulary::logLevel($level),
                "Level '{$level}' must map to '{$expected}'.",
            );
        }
    }
}
