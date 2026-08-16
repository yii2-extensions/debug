<?php

declare(strict_types=1);

namespace yii\debug\helpers;

use yii\log\Logger;

use function strtoupper;

/**
 * Maps HTTP methods, status codes, SQL statement types, and log levels to the fixed semantic hue vocabulary.
 *
 * Single source of truth for the `yii-debug-verb-*`, `yii-debug-status-*`, and `yii-debug-level-*` CSS class suffixes
 * whose hues live in `php-forge/debug-core/resources/src/styles/tokens.css`.
 *
 * The client-side mirrors in `php-forge/debug-core/resources/src/core/history-cursor.js` and
 * `php-forge/debug-core/resources/src/toolbar/element.js` must stay in sync with these maps.
 */
final class Vocabulary
{
    /**
     * Returns the level suffix (`error`, `warning`, `info`, `trace`, `profile`, or `other`) for a {@see Logger} level.
     * Profile begin/end markers share the `profile` hue.
     */
    public static function logLevel(int $level): string
    {
        return match ($level) {
            Logger::LEVEL_ERROR => 'error',
            Logger::LEVEL_WARNING => 'warning',
            Logger::LEVEL_INFO => 'info',
            Logger::LEVEL_TRACE => 'trace',
            Logger::LEVEL_PROFILE, Logger::LEVEL_PROFILE_BEGIN, Logger::LEVEL_PROFILE_END => 'profile',
            default => 'other',
        };
    }

    /**
     * Returns the verb suffix for an SQL statement type, aligning each statement family with its REST counterpart:
     * reads share `get`, `INSERT` shares `post`, in-place mutations share `put`, and destructive statements share
     * `delete`. Unknown types fall back to `other`.
     */
    public static function sqlVerb(string $type): string
    {
        return match (strtoupper($type)) {
            'SELECT', 'SHOW', 'EXPLAIN', 'DESCRIBE', 'PRAGMA' => 'get',
            'INSERT' => 'post',
            'UPDATE', 'REPLACE', 'UPSERT' => 'put',
            'DELETE', 'DROP', 'TRUNCATE' => 'delete',
            default => 'other',
        };
    }

    /**
     * Returns the status-class suffix (`2xx`, `3xx`, `4xx`, or `5xx`) for an HTTP status code, or `none` for
     * uncaptured (`0`) and informational (`1xx`) codes.
     */
    public static function statusClass(int $code): string
    {
        return match (true) {
            $code >= 500 => '5xx',
            $code >= 400 => '4xx',
            $code >= 300 => '3xx',
            $code >= 200 => '2xx',
            default => 'none',
        };
    }

    /**
     * Returns the verb suffix for an HTTP method: `HEAD` shares `get`, `PATCH` shares `put`, and every other
     * non-CRUD method (`OPTIONS`, console `COMMAND`, ...) falls back to `other`.
     */
    public static function verb(string $method): string
    {
        return match (strtoupper($method)) {
            'GET', 'HEAD' => 'get',
            'POST' => 'post',
            'PUT', 'PATCH' => 'put',
            'DELETE' => 'delete',
            default => 'other',
        };
    }
}
