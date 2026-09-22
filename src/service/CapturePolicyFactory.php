<?php

declare(strict_types=1);

namespace yii\debug\service;

use InvalidArgumentException;
use PHPForge\Debug\Capture\CapturePolicy;
use yii\debug\Module;

/**
 * Builds the policy that redacts sensitive values and caps request bodies before the debugger persists a capture.
 *
 * The global rules come from the module configuration; a collector adds its own exact keys on top of them without
 * weakening the shared rules.
 */
class CapturePolicyFactory
{
    /**
     * @param Module $module Debug module read for the redaction configuration at call time.
     */
    public function __construct(protected readonly Module $module) {}

    /**
     * Creates the shared persistent-data policy, optionally extending its exact-key list for one collector.
     *
     * @param list<string> $additionalSensitiveKeys Collector-specific exact keys added without weakening global
     * rules.
     *
     * @throws InvalidArgumentException when the configured keys, prefixes, patterns, or body limit are rejected.
     *
     * @return CapturePolicy Shared policy covering the global rules and the collector-specific keys.
     */
    public function create(array $additionalSensitiveKeys = []): CapturePolicy
    {
        $capturePolicy = new CapturePolicy(
            sensitiveKeys: $this->module->sensitiveKeys,
            maxBodyBytes: $this->module->maxBodyBytes,
            sensitiveKeyPrefixes: $this->module->sensitiveKeyPrefixes,
            sensitiveKeyPatterns: $this->module->sensitiveKeyPatterns,
        );

        return $capturePolicy->withAdditionalSensitiveKeys($additionalSensitiveKeys);
    }
}
