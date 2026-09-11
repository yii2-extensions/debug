<?php

declare(strict_types=1);

namespace yii\debug\tests\support;

use PHPForge\Debug\Panel\Asset\AssetSnapshot;
use PHPForge\Debug\Panel\Config\ConfigSnapshot;
use PHPForge\Debug\Panel\Db\DbSnapshot;
use PHPForge\Debug\Panel\Dump\DumpSnapshot;
use PHPForge\Debug\Panel\Event\EventSnapshot;
use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Panel\Mail\MailSnapshot;
use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use PHPForge\Debug\Panel\Queue\QueueSnapshot;
use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPForge\Debug\Panel\Router\RouterSnapshot;
use PHPForge\Debug\Panel\Timeline\TimelineSnapshot;
use PHPForge\Debug\Panel\User\UserSnapshot;
use yii\debug\collectors\{
    AssetCollector,
    ConfigCollector,
    DbCollector,
    DumpCollector,
    EventCollector,
    LogCollector,
    MailCollector,
    ProfilingCollector,
    QueueCollector,
    RequestCollector,
    RouterCollector,
    TimelineCollector,
    UserCollector,
};

/**
 * Reads a collector's encoded capture back through the snapshot its panel hydrates from.
 *
 * Collectors return the persisted payload, so assertions on typed rows also exercise the encode and decode steps the
 * store applies between capture and presentation.
 */
final class Captured
{
    public static function asset(AssetCollector $collector): AssetSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : AssetSnapshot::fromArray($payload, '$.panels.asset');
    }

    public static function config(ConfigCollector $collector): ConfigSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : ConfigSnapshot::fromArray($payload, '$.panels.config');
    }

    public static function db(DbCollector $collector): DbSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : DbSnapshot::fromArray($payload, '$.panels.db');
    }

    public static function dump(DumpCollector $collector): DumpSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : DumpSnapshot::fromArray($payload, '$.panels.dump');
    }

    public static function event(EventCollector $collector): EventSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : EventSnapshot::fromArray($payload, '$.panels.event');
    }

    public static function log(LogCollector $collector): LogSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : LogSnapshot::fromArray($payload, '$.panels.log');
    }

    public static function mail(MailCollector $collector): MailSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : MailSnapshot::fromArray($payload, '$.panels.mail');
    }

    public static function profiling(ProfilingCollector $collector): ProfilingSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : ProfilingSnapshot::fromArray($payload, '$.panels.profiling');
    }

    public static function queue(QueueCollector $collector): QueueSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : QueueSnapshot::fromArray($payload, '$.panels.queue');
    }

    public static function request(RequestCollector $collector): RequestSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : RequestSnapshot::fromArray($payload, '$.panels.request');
    }

    public static function router(RouterCollector $collector): RouterSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : RouterSnapshot::fromArray($payload, '$.panels.router');
    }

    public static function timeline(TimelineCollector $collector): TimelineSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : TimelineSnapshot::fromArray($payload, '$.panels.timeline');
    }

    public static function user(UserCollector $collector): UserSnapshot|null
    {
        $payload = $collector->capture();

        return $payload === null ? null : UserSnapshot::fromArray($payload, '$.panels.user');
    }
}
