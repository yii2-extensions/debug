<?php

declare(strict_types=1);

namespace yii\debug;

use yii\helpers\IpHelper;

use function gethostbyname;
use function str_contains;
use function strncmp;
use function strpos;

/**
 * Matches a requesting IP against the debugger's allowed IP filters and host allowlist.
 */
final readonly class IpAllowlist
{
    /**
     * @param list<string> $allowedIPs Exact, wildcard, or CIDR IP filters.
     * @param list<string> $allowedHosts Hostnames resolved to IPs at check time.
     */
    public function __construct(
        private array $allowedIPs,
        private array $allowedHosts,
    ) {}

    /**
     * Returns whether the IP matches any allowed IP filter or resolves from any allowed host.
     *
     * @param string $ip Requesting IP address.
     *
     * @return bool `true` when the IP is allowed.
     */
    public function matches(string $ip): bool
    {
        return $this->matchesAllowedIp($ip) || $this->matchesAllowedHost($ip);
    }

    /**
     * Returns whether the IP matches any entry in the host allowlist after DNS resolution.
     */
    private function matchesAllowedHost(string $ip): bool
    {
        foreach ($this->allowedHosts as $hostname) {
            if (gethostbyname($hostname) === $ip) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns whether the IP matches any allowed filter (exact, wildcard, or CIDR).
     */
    private function matchesAllowedIp(string $ip): bool
    {
        foreach ($this->allowedIPs as $filter) {
            if ($filter === '*' || $filter === $ip) {
                return true;
            }

            $wildcardPos = strpos($filter, '*');

            if ($wildcardPos !== false && strncmp($ip, $filter, $wildcardPos) === 0) {
                return true;
            }

            if (str_contains($filter, '/') && IpHelper::inRange($ip, $filter)) {
                return true;
            }
        }

        return false;
    }
}
