<?php

declare(strict_types=1);

namespace yii\debug\panels\config;

use function is_array;
use function is_string;

/**
 * Normalizes the `mixed` payload of {@see \yii\debug\panels\ConfigPanel} into a typed {@see ConfigSummary} tree.
 *
 * Centralizes every `is_array` / `is_string` narrowing previously inlined in the detail view so the rendering layer
 * iterates typed DTOs without further runtime type checks.
 *
 * Usage example:
 * ```php
 * $summary = (new \yii\debug\panels\config\ConfigDataNormalizer())->normalize(
 *     $panel->data,
 *     $panel->getExtensions(),
 * );
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class ConfigDataNormalizer
{
    /**
     * Converts a raw configuration-panel payload into a typed summary.
     *
     * @param mixed $data Raw value of `\yii\debug\panels\ConfigPanel::$data`.
     * @param array<string, string> $extensions Already-typed roster from `ConfigPanel::getExtensions()`.
     */
    public function normalize(mixed $data, array $extensions): ConfigSummary
    {
        $payload = is_array($data) ? $data : [];

        $applicationRaw = is_array($payload['application'] ?? null) ? $payload['application'] : [];
        $phpRaw = is_array($payload['php'] ?? null) ? $payload['php'] : [];

        return new ConfigSummary(
            application: $this->buildApplication($applicationRaw),
            php: $this->buildPhp($phpRaw),
            extensions: $extensions,
        );
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    private function buildApplication(array $raw): ApplicationConfig
    {
        return new ApplicationConfig(
            yii: $this->extractString($raw, 'yii'),
            name: $this->extractString($raw, 'name'),
            version: $this->extractString($raw, 'version'),
            language: $this->extractString($raw, 'language'),
            sourceLanguage: $this->extractString($raw, 'sourceLanguage'),
            charset: $this->extractString($raw, 'charset'),
            env: $this->extractString($raw, 'env'),
            debug: $this->extractBool($raw, 'debug'),
        );
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    private function buildPhp(array $raw): PhpConfig
    {
        return new PhpConfig(
            version: $this->extractString($raw, 'version'),
            xdebug: $this->extractBool($raw, 'xdebug'),
            apcu: $this->extractBool($raw, 'apcu'),
            memcache: $this->extractBool($raw, 'memcache'),
            memcached: $this->extractBool($raw, 'memcached'),
        );
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    private function extractBool(array $raw, string $key): bool
    {
        return (bool) ($raw[$key] ?? false);
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    private function extractString(array $raw, string $key): string
    {
        $value = $raw[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
