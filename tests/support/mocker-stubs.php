<?php

declare(strict_types=1);

/**
 * Stubs map for `xepozz/internal-mocker`.
 *
 * Forces most mocked functions into the variadic `...$arguments` fallback, bypassing the upstream `vendor/xepozz/
 * internal-mocker/src/stubs.php` whose `preg_replace` signature is malformed (`&$count` is declared as required after
 * the optional `$limit = -1`, which PHP 8.4+ rejects with `ArgumentCountError` when the function is called with
 * fewer than five arguments).
 *
 * Hand-crafted signatures are needed for functions with output-by-reference parameters — the variadic fallback drops
 * the back-propagation (`$matches`, `$count`, ...) and breaks the original semantics. List each such function here.
 *
 * @return array<string, array{signatureArguments: string, arguments: string}>
 */
return [];
