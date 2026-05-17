<?php

declare(strict_types=1);

namespace yii\debug\panels\config;

/**
 * Typed view-model for the application section of the Configuration panel.
 *
 * Mirrors the `application` slice of {@see \yii\debug\panels\ConfigPanel::save()} after every value has been narrowed
 * to its declared scalar type; the consuming view reads properties without further type checks.
 */
final readonly class ApplicationConfig
{
    public function __construct(
        /**
         * Yii framework version reported at request capture time.
         */
        public string $yii,
        /**
         * Configured {@see \yii\base\Application::$name}, or empty string when the application is unavailable.
         */
        public string $name,
        /**
         * Configured {@see \yii\base\Application::$version}, or empty string when none is set.
         */
        public string $version,
        /**
         * Configured {@see \yii\base\Application::$language} BCP-47 tag, or empty string.
         */
        public string $language,
        /**
         * Configured {@see \yii\base\Application::$sourceLanguage} BCP-47 tag, or empty string.
         */
        public string $sourceLanguage,
        /**
         * Configured {@see \yii\base\Application::$charset}, or empty string.
         */
        public string $charset,
        /**
         * Active {@see YII_ENV} environment label.
         */
        public string $env,
        /**
         * Active {@see YII_DEBUG} flag.
         */
        public bool $debug,
    ) {}
}
