<?php

declare(strict_types=1);

namespace yii\debug;

/**
 * Names how the application component of an optional provider receives the debugger collector acting as its
 * PSR-14 dispatcher.
 */
enum ProviderAttachment: string
{
    /**
     * The component takes `eventDispatcher` as a constructor argument, so only a definition the application has not
     * instantiated yet can be amended.
     */
    case Constructor = 'constructor';

    /**
     * The component exposes a writable `eventDispatcher` property, so both a live instance and a definition can be
     * amended.
     */
    case Property = 'property';
}
