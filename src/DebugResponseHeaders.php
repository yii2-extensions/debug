<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\Response;

use function array_values;
use function explode;
use function implode;
use function preg_match;
use function trim;

/**
 * Applies defense-in-depth headers to debugger responses without preventing the same-origin toolbar iframe.
 */
final class DebugResponseHeaders
{
    /**
     * Sets the hardening headers and rewrites every Content-Security-Policy value on the response.
     *
     * @param Response $response Debugger response to harden.
     */
    public static function apply(Response $response): void
    {
        $headers = $response->getHeaders();
        $policies = array_values($headers->get('Content-Security-Policy', [], false));

        $headers
            ->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->set('Pragma', 'no-cache')
            ->set('Referrer-Policy', 'no-referrer')
            ->set('X-Content-Type-Options', 'nosniff')
            ->set('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->set('X-Frame-Options', 'SAMEORIGIN');

        $headers->remove('Content-Security-Policy');

        foreach (self::composeContentSecurityPolicies($policies) as $policy) {
            $headers->add('Content-Security-Policy', $policy);
        }
    }

    /**
     * Preserves every host CSP directive while replacing or adding only the debugger frame policy in each header value.
     *
     * Multiple Content-Security-Policy header fields are enforced cumulatively by browsers, so each policy must allow
     * the same-origin toolbar frame instead of collapsing the host policies into a single, potentially weaker value.
     *
     * @param list<string> $policies Existing enforcing CSP header values.
     *
     * @return non-empty-list<string> Composed CSP header values.
     */
    private static function composeContentSecurityPolicies(array $policies): array
    {
        if ($policies === []) {
            return ["frame-ancestors 'self'"];
        }

        $composedPolicies = [];

        foreach ($policies as $policy) {
            $composedDirectives = [];
            $frameAncestorsReplaced = false;

            foreach (explode(';', $policy) as $directive) {
                $directive = trim($directive);

                if ($directive === '') {
                    continue;
                }

                if (preg_match('/^frame-ancestors(?:\s|$)/i', $directive) === 1) {
                    if (!$frameAncestorsReplaced) {
                        $composedDirectives[] = "frame-ancestors 'self'";
                        $frameAncestorsReplaced = true;
                    }

                    continue;
                }

                $composedDirectives[] = $directive;
            }

            if (!$frameAncestorsReplaced) {
                $composedDirectives[] = "frame-ancestors 'self'";
            }

            $composedPolicies[] = implode('; ', $composedDirectives);
        }

        return $composedPolicies;
    }
}
