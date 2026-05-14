<?php

declare(strict_types=1);

namespace yii\debug\panels\config;

use function is_array;
use function is_string;

/**
 * Normalizes the mixed payload of {@see \yii\debug\panels\ConfigPanel} into a typed {@see ConfigSummary} tree.
 *
 * Centralizes every {@see is_array()} / {@see is_string()} narrowing, so the rendering layer iterates typed values
 * without further runtime type checks.
 */
final class ConfigDataNormalizer
{
    /**
     * Converts a raw configuration-panel payload into a typed summary.
     *
     * @param mixed $data Raw value of {@see \yii\debug\panels\ConfigPanel::$data}.
     * @param array<string, string> $extensions Already-typed roster from {@see \yii\debug\panels\ConfigPanel::getExtensions()}.
     *
     * @return ConfigSummary Typed summary safe to render directly.
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
     * Builds the application-section view-model from the `application` slice of the raw payload.
     *
     * @param array<array-key, mixed> $raw Raw `application` slice.
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
     * Builds the PHP-runtime view-model from the `php` slice of the raw payload.
     *
     * @param array<array-key, mixed> $raw Raw `php` slice.
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
     * Coerces the value at `$raw[$key]` to a boolean, falling back to `false` when missing.
     *
     * @param array<array-key, mixed> $raw Slice to read from.
     */
    private function extractBool(array $raw, string $key): bool
    {
        return (bool) ($raw[$key] ?? false);
    }

    /**
     * Returns the value at `$raw[$key]` when it is a string, falling back to `''` otherwise.
     *
     * @param array<array-key, mixed> $raw Slice to read from.
     */
    private function extractString(array $raw, string $key): string
    {
        $value = $raw[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
