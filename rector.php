<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php81\Rector\Array_\ArrayToFirstClassCallableRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->import(__DIR__ . '/vendor/php-forge/coding-standard/src/rector-83.php');

    $rectorConfig->paths(
        [
            __DIR__ . '/src',
            __DIR__ . '/tests',
        ],
    );

    $rectorConfig->skip(
        [
            // `Component::off()` matches handlers with `===`; each first-class callable is a new `Closure` instance,
            // so converting the `[$this, 'method']` pairs in `Module` would break the `on()`/`off()` detach pairing.
            ArrayToFirstClassCallableRector::class,
            // `VarDumper::exportClosure()` tokenizes `T_FUNCTION` only, so an arrow fn fixture exports as an empty
            // string; the long-form closure is the contract under test.
            ClosureToArrowFunctionRector::class => [
                __DIR__ . '/tests/log/LogTargetTest.php',
            ],
        ],
    );
};
