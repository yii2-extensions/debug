<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\asset\{AssetBundleNormalizer, AssetBundleView, AssetSummary};

/**
 * Unit tests for {@see AssetBundleNormalizer} covering payload narrowing, aggregate counters, FQCN splitting,
 * file-label unwrapping and `bodyCols` layout hint computation.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('panel')]
#[Group('asset')]
final class AssetBundleNormalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: int, 2: string}>
     */
    public static function bodyColsCases(): iterable
    {
        yield 'only files' => [
            ['css' => ['a.css']],
            1,
            'Files-only bundles must use a 1-column layout.',
        ];
        yield 'only wiring' => [
            ['baseUrl' => '/assets'],
            1,
            'Wiring-only bundles must use a 1-column layout.',
        ];
        yield 'files + depends' => [
            ['js' => ['a.js'], 'depends' => ['app\\B']],
            2,
            "Files + depends must produce a '2-column' layout.",
        ];
        yield 'files + wiring' => [
            ['css' => ['a.css'], 'sourcePath' => '@app/assets'],
            2,
            "Files + wiring must produce a '2-column' layout.",
        ];
    }

    public function testNormalizeAggregatesTotalsAcrossBundles(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            [
                'app\\A' => ['css' => ['a.css'], 'js' => ['a.js'], 'depends' => ['app\\B']],
                'app\\B' => ['css' => ['b.css', 'c.css'], 'js' => [], 'depends' => []],
                'app\\C' => ['css' => [], 'js' => ['c.js'], 'depends' => ['app\\A', 'app\\B']],
            ],
        );

        self::assertSame(
            3,
            $summary->totalBundles,
            'Total bundles must reflect the input count.',
        );
        self::assertSame(
            3,
            $summary->totalCss,
            'CSS total must sum across every bundle.',
        );
        self::assertSame(
            2,
            $summary->totalJs,
            'JS total must sum across every bundle.',
        );
        self::assertSame(
            3,
            $summary->totalDeps,
            'Deps total must sum across every bundle.',
        );
    }

    public function testNormalizeAssignsCamelCaseAnchorId(): void
    {
        $simple = $this->firstBundle((new AssetBundleNormalizer())->normalize(['MyCoolAsset' => []]));
        $namespaced = $this->firstBundle((new AssetBundleNormalizer())->normalize(['app\\assets\\MyCoolAsset' => []]));

        self::assertSame(
            'my-cool-asset',
            $simple->id,
            'Bare CamelCase must dash-separate to lower case.',
        );
        self::assertSame(
            'app\\assets\\-my-cool-asset',
            $namespaced->id,
            "Inflector treats '\\' as a non-camel boundary and prepends a dash before the basename.",
        );
    }

    /**
     * @param array<string, mixed> $bundle
     */
    #[DataProvider('bodyColsCases')]
    public function testNormalizeComputesBodyCols(array $bundle, int $expected, string $message): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(['app\\AppAsset' => $bundle]);

        self::assertSame($expected, $this->firstBundle($summary)->bodyCols, $message);
    }

    public function testNormalizeDropsNonStringDependEntries(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            [
                'app\\AppAsset' => ['depends' => ['app\\B', 42, null, 'app\\C', false]],
            ],
        );

        $bundle = $this->firstBundle($summary);

        self::assertSame(
            ['app\\B', 'app\\C'],
            $bundle->depends,
            'Non-string deps must be filtered out.',
        );
        self::assertSame(
            2,
            $bundle->depsCount,
            'Deps count must reflect the filtered list.',
        );
    }

    public function testNormalizeFallsBackToEmptyArrayWhenCssIsNotAnArray(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            [
                'app\\AppAsset' => ['css' => 'invalid', 'js' => null, 'depends' => 42],
            ],
        );

        $bundle = $this->firstBundle($summary);

        self::assertSame(
            [],
            $bundle->css,
            "Non-array 'css' must collapse to '[]'.",
        );
        self::assertSame(
            [],
            $bundle->js,
            "Non-array 'js' must collapse to '[]'.",
        );
        self::assertSame(
            [],
            $bundle->depends,
            "Non-array 'depends' must collapse to '[]'.",
        );
    }

    public function testNormalizeFallsBackToEmptyStringWhenWiringFieldsAreNotStrings(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            [
                'app\\AppAsset' => ['sourcePath' => 12, 'basePath' => null, 'baseUrl' => false],
            ],
        );

        $bundle = $this->firstBundle($summary);

        self::assertSame(
            '',
            $bundle->sourcePath,
            "Non-string 'sourcePath' must collapse to ''",
        );
        self::assertSame(
            '',
            $bundle->basePath,
            "Non-string 'basePath' must collapse to ''",
        );
        self::assertSame(
            '',
            $bundle->baseUrl,
            "Non-string 'baseUrl' must collapse to ''",
        );
        self::assertFalse(
            $bundle->hasWiring,
            "No wiring values means 'hasWiring' is 'false'.",
        );
    }

    public function testNormalizeFlagsHasFilesWhenAnyFilePresent(): void
    {
        $cssOnly = $this->firstBundle(
            (new AssetBundleNormalizer())->normalize(
                [
                    'app\\AppAsset' => ['css' => ['a.css']],
                ],
            ),
        );
        $jsOnly = $this->firstBundle(
            (new AssetBundleNormalizer())->normalize(
                [
                    'app\\AppAsset' => ['js' => ['a.js']],
                ],
            ),
        );
        $empty = $this->firstBundle(
            (new AssetBundleNormalizer())->normalize(
                [
                    'app\\AppAsset' => [],
                ],
            ),
        );

        self::assertTrue(
            $cssOnly->hasFiles,
            "CSS presence must set 'hasFiles'.",
        );
        self::assertTrue(
            $jsOnly->hasFiles,
            "JS presence must set 'hasFiles'.",
        );
        self::assertFalse(
            $empty->hasFiles,
            'Empty bundles must not be flagged as having files.',
        );
    }

    public function testNormalizeKeepsBundleOrderFromInput(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            [
                'z\\Zebra' => [],
                'a\\Apple' => [],
                'm\\Mango' => [],
            ],
        );

        self::assertSame(
            ['z\\Zebra', 'a\\Apple', 'm\\Mango'],
            array_map(static fn(AssetBundleView $b): string => $b->name, $summary->bundles),
            'Insertion order must be preserved.',
        );
    }

    public function testNormalizeReturnsEmptySummaryWhenDataIsEmpty(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            [],
        );

        self::assertTrue(
            $summary->isEmpty(),
            'Empty array must yield an empty summary.',
        );
        self::assertSame(
            [],
            $summary->bundles,
            'Bundle list must be the empty list.',
        );
    }

    public function testNormalizeReturnsEmptySummaryWhenDataIsNotArray(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            'not an array'
        );

        self::assertTrue(
            $summary->isEmpty(),
            'Non-array input must yield an empty summary.',
        );
        self::assertSame(
            0,
            $summary->totalBundles,
            "Total bundles must be '0'.",
        );
        self::assertSame(
            0,
            $summary->totalCss,
            "Total CSS must be '0'.",
        );
        self::assertSame(
            0,
            $summary->totalJs,
            "Total JS must be '0'.",
        );
        self::assertSame(
            0,
            $summary->totalDeps,
            "Total deps must be '0'.",
        );
    }

    public function testNormalizeSkipsEntriesWithNonArrayBundle(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            [
                'app\\BadAsset' => 'not an array',
                'app\\AppAsset' => ['css' => ['app.css']],
            ],
        );

        $bundle = $this->firstBundle($summary);

        self::assertCount(
            1,
            $summary->bundles,
            'Non-array bundle payloads must be skipped.',
        );
        self::assertSame(
            'app\\AppAsset',
            $bundle->name,
            'Only the well-formed bundle must survive.',
        );
    }

    public function testNormalizeSkipsEntriesWithNonStringName(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            [
                0 => ['css' => ['style.css']],
                'app\\AppAsset' => ['css' => ['app.css']],
            ],
        );

        $bundle = $this->firstBundle($summary);

        self::assertCount(
            1,
            $summary->bundles,
            'Numeric keys must be skipped.',
        );
        self::assertSame(
            'app\\AppAsset',
            $bundle->name,
            'Only the string-keyed bundle must survive.',
        );
    }

    public function testNormalizeSplitsFqcnIntoShortNameAndNamespace(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            [
                'vendor\\package\\sub\\MyAsset' => [],
                'Standalone' => [],
            ],
        );

        $first = $summary->bundles[0] ?? self::fail('First bundle must exist.');
        $second = $summary->bundles[1] ?? self::fail('Second bundle must exist.');

        self::assertSame(
            'MyAsset',
            $first->shortName,
            'Short name must be the last FQCN segment.',
        );
        self::assertSame(
            'vendor\\package\\sub',
            $first->namespace,
            "Namespace must omit trailing '\\'.",
        );
        self::assertSame(
            'Standalone',
            $second->shortName,
            "Names without '\\' keep their full value.",
        );
        self::assertSame(
            '',
            $second->namespace,
            "Names without '\\' produce an empty namespace.",
        );
    }

    public function testNormalizeUnwrapsArrayFileEntriesViaFirstElement(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(
            [
                'app\\AppAsset' => [
                    'css' => [
                        'plain.css',
                        [
                            '<a href="x">wrapped.css</a>',
                            'extra-ignored',
                        ],
                    ],
                    'js' => [
                        [],
                        'plain.js',
                    ],
                ],
            ],
        );

        $bundle = $this->firstBundle($summary);

        self::assertSame(
            ['plain.css', '<a href="x">wrapped.css</a>'],
            $bundle->css,
            'Wrapped entries: first element kept.',
        );
        self::assertSame(
            ['plain.js'],
            $bundle->js,
            'Empty inner arrays must be dropped.',
        );
    }

    /**
     * Returns the first bundle of a summary, failing the test when the list is empty.
     *
     * Wrap-once helper that gives us a non-nullable {@see AssetBundleView} so PHPStan does not flag every
     * `$summary->bundles[0]->...` access as a possibly-missing offset.
     */
    private function firstBundle(AssetSummary $summary): AssetBundleView
    {
        return $summary->bundles[0] ?? self::fail('Expected at least one bundle in the summary.');
    }
}
