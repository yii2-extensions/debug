<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function file_get_contents;
use function str_contains;
use function strlen;
use function substr;

/**
 * Guards the host against naming any provider package: Vite and Inertia register through `collectors`, `panels`, and
 * `dispatchers` like every other extension, so no source file may reference their namespaces.
 */
#[Group('module')]
final class ProviderNeutralityTest extends TestCase
{
    /**
     * Namespaces owned by provider packages the host must never name.
     */
    private const array PROVIDER_NAMESPACES = ['PHPForge\\Inertia', 'PHPForge\\Vite', 'yii\\inertia'];

    public function testSourceNamesNoProviderPackage(): void
    {
        $root = dirname(__DIR__);

        $offenders = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/src"));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->isFile() === false || $file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            foreach (self::PROVIDER_NAMESPACES as $namespace) {
                if (str_contains($source, $namespace)) {
                    $offenders[] = substr($file->getPathname(), strlen($root) + 1) . " ({$namespace})";
                }
            }
        }

        self::assertSame(
            [],
            $offenders,
            'Files naming a provider namespace.',
        );
    }
}
