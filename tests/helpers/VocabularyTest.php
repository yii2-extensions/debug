<?php

declare(strict_types=1);

namespace yii\debug\tests\helpers;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\helpers\Vocabulary;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

/**
 * Unit tests for {@see Vocabulary} covering the canonical verb, status-class, SQL, and log-level hue maps.
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

    public function testSqlVerbMapsStatementFamiliesToRestVerbs(): void
    {
        $mappings = [
            'SELECT' => 'get', 'SHOW' => 'get', 'EXPLAIN' => 'get', 'DESCRIBE' => 'get', 'PRAGMA' => 'get',
            'INSERT' => 'post',
            'UPDATE' => 'put', 'REPLACE' => 'put', 'UPSERT' => 'put',
            'DELETE' => 'delete', 'DROP' => 'delete', 'TRUNCATE' => 'delete',
            'select' => 'get',
            'Insert' => 'post',
            'BOGUS' => 'other',
            '' => 'other',
        ];

        foreach ($mappings as $type => $expected) {
            self::assertSame(
                $expected,
                Vocabulary::sqlVerb($type),
                "Statement '{$type}' must map to '{$expected}'.",
            );
        }
    }

    public function testStatusClassMapsRangeBoundariesToStatusSuffixes(): void
    {
        $mappings = [
            0 => 'none',
            100 => 'none',
            199 => 'none',
            200 => '2xx',
            299 => '2xx',
            300 => '3xx',
            399 => '3xx',
            400 => '4xx',
            499 => '4xx',
            500 => '5xx',
            599 => '5xx',
            999 => '5xx',
        ];

        foreach ($mappings as $code => $expected) {
            self::assertSame(
                $expected,
                Vocabulary::statusClass($code),
                "Code '{$code}' must map to '{$expected}'.",
            );
        }
    }

    public function testVerbMapsHttpMethodsToVocabularySuffixes(): void
    {
        $mappings = [
            'GET' => 'get', 'HEAD' => 'get',
            'POST' => 'post',
            'PUT' => 'put', 'PATCH' => 'put',
            'DELETE' => 'delete',
            'get' => 'get',
            'pAtCh' => 'put',
            'OPTIONS' => 'other',
            'TRACE' => 'other',
            'CONNECT' => 'other',
            'COMMAND' => 'other',
            '' => 'other',
            'BOGUS' => 'other',
        ];

        foreach ($mappings as $method => $expected) {
            self::assertSame(
                $expected,
                Vocabulary::verb($method),
                "Method '{$method}' must map to '{$expected}'.",
            );
        }
    }
}
