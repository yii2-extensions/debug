<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\IpAllowlist;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see IpAllowlist} IP filters (exact, wildcard, CIDR) and the DNS-resolved host allowlist.
 */
#[Group('module')]
final class IpAllowlistTest extends TestCase
{
    public function testMatchesCidrRange(): void
    {
        $checker = new IpAllowlist(['172.16.0.0/12'], []);

        self::assertTrue($checker->matches('172.20.1.9'), 'In-range IP must match the CIDR filter.');
        self::assertFalse($checker->matches('172.32.0.1'), 'Out-of-range IP must not match the CIDR filter.');
    }

    public function testMatchesExactIpOnly(): void
    {
        $checker = new IpAllowlist(['192.168.0.7'], []);

        self::assertTrue($checker->matches('192.168.0.7'), 'Exact IP must match.');
        self::assertFalse($checker->matches('192.168.0.8'), 'Different IP must not match.');
    }

    public function testMatchesHostnameThroughDnsResolution(): void
    {
        $checker = new IpAllowlist([], ['localhost']);

        self::assertTrue($checker->matches('127.0.0.1'), 'Resolved host IP must match.');
        self::assertFalse($checker->matches('10.9.9.9'), 'Unresolved IP must not match any host.');
    }

    public function testMatchesNothingWithoutFilters(): void
    {
        self::assertFalse((new IpAllowlist([], []))->matches('127.0.0.1'), 'Empty filters must deny.');
    }

    public function testMatchesWildcardPrefix(): void
    {
        $checker = new IpAllowlist(['192.168.0.*'], []);

        self::assertTrue($checker->matches('192.168.0.44'), 'Prefix wildcard must match the shared prefix.');
        self::assertFalse($checker->matches('192.168.1.44'), 'Prefix wildcard must reject other prefixes.');
        self::assertTrue((new IpAllowlist(['*'], []))->matches('8.8.8.8'), 'Bare `*` must match everything.');
    }
}
