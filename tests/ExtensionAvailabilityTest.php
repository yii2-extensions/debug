<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPUnit\Framework\Attributes\Group;
use Xepozz\InternalMocker\MockerState;
use yii\debug\ExtensionAvailability;
use yii\debug\panels\{JsonPanel, MailPanel, ProviderPanel, QueuePanel, RequestPanel};
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see ExtensionAvailability} covering extension-panel classification and runtime provider detection.
 */
#[Group('module')]
final class ExtensionAvailabilityTest extends TestCase
{
    public function testIdsWithoutProviderAreAvailable(): void
    {
        MockerState::addCondition(
            'yii\debug',
            'class_exists',
            ['yii\symfonymailer\Mailer'],
            false,
        );

        foreach (['mail', 'request'] as $id) {
            self::assertTrue(
                ExtensionAvailability::isAvailable($id),
                "The '{$id}' panel must not depend on a runtime provider class.",
            );
        }
    }

    public function testIsAvailableAcceptsInstalledSingleClassProviders(): void
    {
        $providers = [
            'inertia' => 'PHPForge\Inertia\Debug\InertiaCollector',
            'queue' => 'yii\queue\Queue',
        ];

        foreach ($providers as $id => $provider) {
            MockerState::addCondition(
                'yii\debug',
                'class_exists',
                [$provider],
                true,
            );

            self::assertTrue(
                ExtensionAvailability::isAvailable($id),
                "The installed provider for '{$id}' must make the integration available.",
            );
        }
    }

    public function testIsAvailableRejectsMissingProviders(): void
    {
        $providers = [
            'PHPForge\Inertia\Debug\InertiaCollector',
            'yii\queue\Queue',
        ];

        foreach ($providers as $provider) {
            MockerState::addCondition(
                'yii\debug',
                'class_exists',
                [$provider],
                false,
            );
        }

        $ids = [
            'inertia',
            'queue',
        ];

        foreach ($ids as $id) {
            self::assertFalse(
                ExtensionAvailability::isAvailable($id),
                "The '{$id}' integration must be unavailable without one of its provider classes.",
            );
        }
    }

    public function testIsExtensionPanelClassifiesOnlyProviderAndPayloadPanels(): void
    {
        self::assertTrue(
            ExtensionAvailability::isExtensionPanel('vite', new ProviderPanel()),
            'A provider-backed panel belongs to the extensions.',
        );
        self::assertTrue(
            ExtensionAvailability::isExtensionPanel('dump', new JsonPanel()),
            'A payload-only panel belongs to the extensions.',
        );
        self::assertTrue(
            ExtensionAvailability::isExtensionPanel('inertia', new RequestPanel()),
            'A packaged provider id belongs to the extensions.',
        );
        self::assertFalse(
            ExtensionAvailability::isExtensionPanel('mail', new MailPanel()),
            'Mail must stay built-in.',
        );
        self::assertFalse(
            ExtensionAvailability::isExtensionPanel('queue', new QueuePanel()),
            'Queue must stay built-in although its package is optional.',
        );
        self::assertFalse(
            ExtensionAvailability::isExtensionPanel('request', new RequestPanel()),
            'Core diagnostics must stay built-in.',
        );
    }
}
