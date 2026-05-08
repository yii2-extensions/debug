<?php

declare(strict_types=1);

namespace yii\debug\panels\config;

/**
 * Typed view-model for the application section of the Configuration panel.
 *
 * Mirrors the `application` slice of {@see \yii\debug\panels\ConfigPanel::save()} after every value has been narrowed
 * to its declared scalar type; the consuming view reads properties without further type checks.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class ApplicationConfig
{
    public function __construct(
        /**
         * Yii framework version reported at request capture time.
         */
        public string $yii,
        /**
         * Configured `Application::$name`, or empty string when the application is unavailable.
         */
        public string $name,
        /**
         * Configured `Application::$version`, or empty string when none is set.
         */
        public string $version,
        /**
         * Configured `Application::$language` BCP-47 tag, or empty string.
         */
        public string $language,
        /**
         * Configured `Application::$sourceLanguage` BCP-47 tag, or empty string.
         */
        public string $sourceLanguage,
        /**
         * Configured `Application::$charset`, or empty string.
         */
        public string $charset,
        /**
         * Active `YII_ENV` environment label.
         */
        public string $env,
        /**
         * Active `YII_DEBUG` flag.
         */
        public bool $debug,
    ) {}
}
