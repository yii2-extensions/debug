<?php

declare(strict_types=1);

namespace yii\debug\tests\helpers;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\helpers\Fqcn;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see Fqcn} covering the shared two-tone label markup: namespace/short-name splitting, method-suffix
 * handling, the plain-value and empty-value fallbacks, and the `title` attribute.
 */
#[Group('helpers')]
#[Group('fqcn')]
final class FqcnTest extends TestCase
{
    public function testRenderLabelExposesFullValueInTitleAttribute(): void
    {
        self::assertStringContainsString(
            'title="yii\base\Event"',
            Fqcn::renderLabel('yii\\base\\Event'),
            'Full value must sit in the `title` attribute.',
        );
    }

    public function testRenderLabelKeepsMethodSuffixInsideStrongShortName(): void
    {
        $label = Fqcn::renderLabel('yii\\db\\Command::query');

        self::assertStringContainsString(
            '<strong>Command::query</strong>',
            $label,
            'Method pair must render bold as one segment.',
        );
        self::assertStringContainsString(
            'yii\db\\',
            $label,
            'Namespace prefix must keep its trailing separator.',
        );
    }

    public function testRenderLabelOmitsMutedPrefixForPlainValues(): void
    {
        $label = Fqcn::renderLabel('application');

        self::assertStringContainsString(
            '<strong>application</strong>',
            $label,
            'Plain value must render bold.',
        );
        self::assertStringNotContainsString(
            'yii-debug-muted',
            $label,
            'Plain values must not emit a namespace prefix.',
        );
    }

    public function testRenderLabelRendersEmDashForEmptyValue(): void
    {
        self::assertSame(
            '—',
            Fqcn::renderLabel(''),
            'Empty value must collapse to an em dash.',
        );
    }

    public function testRenderLabelSplitsNamespacedValueIntoMutedNamespaceAndStrongShortName(): void
    {
        $label = Fqcn::renderLabel('yii\\base\\Event');

        self::assertSame(
            <<<HTML
            <span title="yii\base\Event"><span class="yii-debug-muted">yii\base\</span><wbr><strong>Event</strong></span>
            HTML,
            $label,
            'Namespaced labels must keep the namespace, break opportunity, and short name in display order.',
        );
        self::assertStringContainsString(
            'yii-debug-muted',
            $label,
            'Namespace prefix must render muted.',
        );
        self::assertStringContainsString(
            '<strong>Event</strong>',
            $label,
            'Short name must render bold.',
        );
    }
}
