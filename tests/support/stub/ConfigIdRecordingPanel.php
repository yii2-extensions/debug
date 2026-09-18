<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\debug\Panel;

/**
 * Stub panel that records the raw `id` entry it receives in its configuration and registers under a fixed ID.
 *
 * The fixed ID keeps a numeric registration key out of the {@see \yii\debug\Module::$panels} keys, so the recorded
 * value stays observable after the panel is registered.
 */
final class ConfigIdRecordingPanel extends Panel
{
    /**
     * ID the panel registers under, whatever the registration key is.
     */
    public const string ID = 'config-id-recording';

    /**
     * Raw `id` entry received in the configuration, before the component applies it.
     */
    public mixed $configuredId = null;

    /**
     * @param array<string, mixed> $config Component configuration.
     */
    public function __construct(array $config = [])
    {
        $this->configuredId = $config['id'] ?? null;

        $config['id'] = self::ID;

        parent::__construct($config);
    }
}
