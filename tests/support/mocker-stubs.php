<?php

declare(strict_types=1);

/**
 * Empty stubs map for `xepozz/internal-mocker`.
 *
 * Forces every mocked function into the variadic `...$arguments` fallback, bypassing the upstream `vendor/xepozz/
 * internal-mocker/src/stubs.php` whose `preg_replace` signature is malformed (`&$count` is declared as required after
 * the optional `$limit = -1`, which PHP 8.4+ rejects with `ArgumentCountError` when the function is called with
 * fewer than five arguments).
 *
 * @return array<string, array{signatureArguments: string, arguments: string}>
 */
return [];
