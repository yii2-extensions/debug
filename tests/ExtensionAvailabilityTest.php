<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPUnit\Framework\Attributes\Group;
use Xepozz\InternalMocker\MockerState;
use yii\debug\ExtensionAvailability;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see ExtensionAvailability} covering optional-ID classification and runtime provider detection.
 */
#[Group('module')]
final class ExtensionAvailabilityTest extends TestCase
{
    public function testIsAvailableAcceptsInstalledSingleClassProviders(): void
    {
        foreach (
            [
                'inertia' => 'yii\inertia\Manager',
                'mail' => 'yii\symfonymailer\Mailer',
                'queue' => 'yii\queue\Queue',
            ] as $id => $provider
        ) {
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

    public function testIsAvailableAcceptsLegacyViteProvider(): void
    {
        MockerState::addCondition(
            'yii\debug',
            'class_exists',
            ['PHPForge\Vite\Vite'],
            false,
        );
        MockerState::addCondition(
            'yii\debug',
            'class_exists',
            ['yii\inertia\Vite'],
            true,
        );

        self::assertTrue(
            ExtensionAvailability::isAvailable('vite'),
            'The legacy Inertia Vite implementation must keep the Vite integration available.',
        );
    }

    public function testIsAvailableAcceptsModernViteProviderWithoutCheckingTheLegacyProvider(): void
    {
        MockerState::addCondition(
            'yii\debug',
            'class_exists',
            ['PHPForge\Vite\Vite'],
            true,
        );
        MockerState::addCondition(
            'yii\debug',
            'class_exists',
            ['yii\inertia\Vite'],
            false,
        );

        self::assertTrue(
            ExtensionAvailability::isAvailable('vite'),
            'The modern PHPForge Vite implementation must make the integration available.',
        );
    }

    public function testIsAvailableRejectsMissingProviders(): void
    {
        foreach (
            [
                'yii\inertia\Manager',
                'yii\symfonymailer\Mailer',
                'yii\queue\Queue',
                'PHPForge\Vite\Vite',
                'yii\inertia\Vite',
            ] as $provider
        ) {
            MockerState::addCondition(
                'yii\debug',
                'class_exists',
                [$provider],
                false,
            );
        }

        foreach (['inertia', 'mail', 'queue', 'vite'] as $id) {
            self::assertFalse(
                ExtensionAvailability::isAvailable($id),
                "The '{$id}' integration must be unavailable without one of its provider classes.",
            );
        }
    }

    public function testKnownIdsAreOptional(): void
    {
        foreach (['mail', 'queue', 'inertia', 'vite'] as $id) {
            self::assertTrue(
                ExtensionAvailability::isOptional($id),
                "The '{$id}' integration must be classified as optional.",
            );
        }
    }

    public function testUnknownIdIsAvailableButNotOptional(): void
    {
        self::assertTrue(
            ExtensionAvailability::isAvailable('request'),
            'Core Yii diagnostics must not require an optional provider.',
        );
        self::assertFalse(
            ExtensionAvailability::isOptional('request'),
            'Core Yii diagnostics must not be classified as optional integrations.',
        );
    }
}
