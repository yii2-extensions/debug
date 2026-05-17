<?php

declare(strict_types=1);

namespace yii\debug\tests\asset;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\asset\{AssetBundleNormalizer, AssetCardRenderer};
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see AssetCardRenderer} covering anchor resolution, chip pluralization, and the rendered article
 * structure for representative bundle states.
 */
#[Group('panel')]
#[Group('asset')]
final class AssetCardRendererTest extends TestCase
{
    public function testRenderCardEmitsArticleWithBundleAnchorId(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            ['app\\AppAsset' => ['css' => ['style.css']]],
        );

        $bundle = $summary->bundles[0] ?? self::fail('Expected one bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertStringContainsString(
            'class="yii-debug-asset-card"',
            $html,
            'Card must carry the wrapper class.',
        );
        self::assertStringContainsString(
            "id=\"{$bundle->id}\"",
            $html,
            'Card must expose the bundle anchor id.',
        );
    }

    public function testRenderCardEmitsJsFilesListAndChipForJsOnlyBundle(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            ['app\\AppAsset' => ['js' => ['app.js']]],
        );

        $bundle = $summary->bundles[0] ?? self::fail('Expected one bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertStringContainsString(
            '<strong>1</strong> js<',
            $html,
            "JS-only bundle must render the 'js' chip.",
        );
        self::assertStringContainsString(
            'yii-debug-asset-file-type-js',
            $html,
            'JS file row must carry the type modifier class.',
        );
        self::assertStringContainsString(
            'app.js',
            $html,
            'JS file label must be rendered.',
        );
    }

    public function testRenderCardEmitsPluralChipForMultipleDependencies(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            [
                'app\\AppAsset' => [
                    'depends' => [
                        'app\\A',
                        'app\\B',
                        'app\\C',
                    ],
                ],
            ],
        );

        $bundle = $summary->bundles[0] ?? self::fail('Expected one bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertStringContainsString(
            '<strong>3</strong> deps<',
            $html,
            "Multiple dependencies must read 'N deps'.",
        );
    }

    public function testRenderCardEmitsShortNameAndNamespacePrefix(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            ['vendor\\package\\AppAsset' => []],
        );

        $bundle = $summary->bundles[0] ?? self::fail('Expected one bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertMatchesRegularExpression(
            '/>\s*AppAsset\s*</',
            $html,
            'Header must render the bundle short name.',
        );
        self::assertStringContainsString(
            'vendor\\package\\',
            $html,
            'Header must render the namespace prefix.',
        );
    }

    public function testRenderCardEmitsSingularChipForOneDependency(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            ['app\\AppAsset' => ['depends' => ['app\\Other']]],
        );

        $bundle = $summary->bundles[0] ?? self::fail('Expected one bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertStringContainsString(
            '<strong>1</strong> dep<',
            $html,
            "Single dependency must read '1 dep'.",
        );
        self::assertStringNotContainsString(
            '1</strong> deps<',
            $html,
            "Singular form must not pluralize to 'deps'.",
        );
    }

    public function testRenderCardEmitsTwoColumnLayoutWhenFilesAndWiringPresent(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            [
                'app\\AppAsset' => [
                    'css' => ['app.css'],
                    'sourcePath' => '@app/assets',
                ],
            ],
        );

        $bundle = $summary->bundles[0] ?? self::fail('Expected one bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertStringContainsString(
            'data-cols="2"',
            $html,
            "Files + wiring must produce a '2-column' body.",
        );
        self::assertStringContainsString(
            'Files',
            $html,
            'Files section heading must be present.',
        );
        self::assertStringContainsString(
            'Wiring',
            $html,
            'Wiring section heading must be present.',
        );
    }

    public function testRenderCardLinksDependencyToRegisteredAnchor(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            [
                'app\\AppAsset' => ['depends' => ['app\\Target']],
                'app\\Target' => [],
            ],
        );

        $bundle = $summary->bundles[0] ?? self::fail('Expected the source bundle.');
        $target = $summary->bundles[1] ?? self::fail('Expected the target bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertStringContainsString(
            "href=\"#{$target->id}\"",
            $html,
            'Dep link must target the registered anchor.',
        );
        self::assertStringContainsString(
            'title="app\\Target"',
            $html,
            'Dep link must keep the FQCN in the title.',
        );
        self::assertStringContainsString(
            '>Target<',
            $html,
            'Dep link must show the short class name.',
        );
    }

    public function testRenderCardOmitsBodyWhenBundleHasNoFilesOrWiringOrDeps(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            ['app\\BareAsset' => []],
        );

        $bundle = $summary->bundles[0] ?? self::fail('Expected one bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertStringNotContainsString(
            'yii-debug-asset-card-body',
            $html,
            'Empty bundles must omit the card body.',
        );
        self::assertStringNotContainsString(
            'yii-debug-asset-section',
            $html,
            'No body means no sections.',
        );
    }

    public function testRenderCardWiringRendersBasePathRow(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            ['app\\AppAsset' => ['basePath' => '@webroot/assets']],
        );

        $bundle = $summary->bundles[0] ?? self::fail('Expected one bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertMatchesRegularExpression(
            '/>\s*base\s*</',
            $html,
            "Populated 'basePath' must render its row.",
        );
        self::assertStringContainsString(
            '@webroot/assets',
            $html,
            "Populated 'basePath' value must be rendered.",
        );
    }

    public function testRenderCardWiringRendersOnlyPopulatedFields(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            ['app\\AppAsset' => ['baseUrl' => '/assets']],
        );

        $bundle = $summary->bundles[0] ?? self::fail('Expected one bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertMatchesRegularExpression(
            '/>\s*url\s*</',
            $html,
            "Populated 'baseUrl' must render its row.",
        );
        self::assertStringContainsString(
            '/assets',
            $html,
            "Populated 'baseUrl' value must be rendered.",
        );
        self::assertDoesNotMatchRegularExpression(
            '/>\s*source\s*</',
            $html,
            "Empty 'sourcePath' must not render a row.",
        );
        self::assertDoesNotMatchRegularExpression(
            '/>\s*base\s*</',
            $html,
            "Empty 'basePath' must not render a row.",
        );
    }

    public function testResolveAnchorFallsBackToCamel2idForUnregisteredDep(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            ['app\\AppAsset' => []],
        );

        self::assertSame(
            'unknown\\package\\-stranger-asset',
            AssetCardRenderer::resolveAnchor('unknown\\package\\StrangerAsset', $summary),
            "Unregistered deps must use the same 'Inflector::camel2id()' rule as registered ones.",
        );
    }

    public function testResolveAnchorReturnsRegisteredBundleId(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            [
                'app\\AppAsset' => [],
                'app\\OtherAsset' => [],
            ],
        );

        $bundle = $summary->bundles[1] ?? self::fail('Expected a second bundle.');

        self::assertSame(
            $bundle->id,
            AssetCardRenderer::resolveAnchor('app\\OtherAsset', $summary),
            'Registered deps must resolve to the matching card id.',
        );
    }
}
