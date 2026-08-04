<?php

declare(strict_types=1);

namespace yii\debug\widgets\phpinfo;

use function array_fill_keys;
use function array_keys;
use function in_array;
use function strtolower;

/**
 * Groups {@see phpinfo()} modules by the job they perform.
 *
 * Drives the TOC sidebar layout, the "Loaded extensions" buckets, and the extension/non-extension split that keeps
 * PHP Variables, PHP Credits, and the other reporting blocks out of the extension list.
 *
 * Usage example:
 * ```php
 * \yii\debug\widgets\phpinfo\PhpInfoModuleGroup::resolve('pdo_mysql');    // 'Database'
 * \yii\debug\widgets\phpinfo\PhpInfoModuleGroup::isExtension('PHP License'); // false
 * ```
 */
final class PhpInfoModuleGroup
{
    /**
     * Reporting blocks that {@see phpinfo()} emits as modules but that describe the environment, not an extension.
     */
    public const string ENVIRONMENT = 'Environment';

    /**
     * Bucket for unknown and third-party modules.
     */
    public const string OTHER = 'Other';

    /**
     * Lower-cased module titles per group, in TOC display order.
     *
     * @var array<string, list<string>>
     */
    private const array GROUPS = [
        'Core & Runtime' => [
            'apcu',
            'core',
            'date',
            'ffi',
            'filter',
            'hash',
            'json',
            'pcre',
            'phar',
            'random',
            'reflection',
            'session',
            'spl',
            'standard',
            'xdebug',
            'zend opcache',
        ],
        'Database' => [
            'mongodb',
            'mysqli',
            'mysqlnd',
            'oci8',
            'odbc',
            'pdo',
            'pdo_dblib',
            'pdo_mysql',
            'pdo_oci',
            'pdo_odbc',
            'pdo_pgsql',
            'pdo_sqlite',
            'pgsql',
            'redis',
            'sqlite3',
        ],
        'Text & Localization' => [
            'ctype',
            'gettext',
            'iconv',
            'intl',
            'mbstring',
            'tokenizer',
        ],
        'Network & Security' => [
            'curl',
            'ftp',
            'openssl',
            'sockets',
            'sodium',
            'ssh2',
        ],
        'XML, Data & Media' => [
            'dom',
            'exif',
            'fileinfo',
            'gd',
            'imagick',
            'libxml',
            'simplexml',
            'xml',
            'xmlreader',
            'xmlwriter',
            'xsl',
        ],
        'System & Compression' => [
            'bz2',
            'calendar',
            'pcntl',
            'posix',
            'readline',
            'shmop',
            'sysvmsg',
            'sysvsem',
            'sysvshm',
            'zip',
            'zlib',
        ],
        self::ENVIRONMENT => [
            'additional modules',
            'apache2handler',
            'cgi-fcgi',
            'environment',
            'php credits',
            'php license',
            'php variables',
        ],
    ];

    /**
     * Buckets items by the group owning their module title, keeping every label in TOC display order with
     * {@see OTHER} last. Groups nothing lands in stay empty rather than disappearing, so callers decide whether to
     * skip them.
     *
     * @template T
     *
     * @param list<T> $items Items to bucket.
     * @param callable(T): string $title Reads the module title from an item.
     *
     * @return array<string, list<T>> Items per group label.
     */
    public static function bucket(array $items, callable $title): array
    {
        $buckets = array_fill_keys([...array_keys(self::GROUPS), self::OTHER], []);

        foreach ($items as $item) {
            $buckets[self::resolve($title($item))][] = $item;
        }

        return $buckets;
    }

    /**
     * Whether the module describes a PHP extension rather than a reporting block.
     *
     * @param string $title Module title reported by {@see phpinfo()}.
     */
    public static function isExtension(string $title): bool
    {
        return self::resolve($title) !== self::ENVIRONMENT;
    }

    /**
     * Returns the group label owning the module, or {@see OTHER} when the module is unknown.
     *
     * @param string $title Module title reported by {@see phpinfo()}.
     */
    public static function resolve(string $title): string
    {
        $normalizedTitle = strtolower($title);

        foreach (self::GROUPS as $label => $modules) {
            if (in_array($normalizedTitle, $modules, true)) {
                return $label;
            }
        }

        return self::OTHER;
    }
}
