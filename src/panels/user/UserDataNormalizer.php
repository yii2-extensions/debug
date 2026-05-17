<?php

declare(strict_types=1);

namespace yii\debug\panels\user;

use yii\debug\helpers\Icon;

use function ctype_digit;
use function date;
use function floor;
use function in_array;
use function is_array;
use function mb_strtoupper;
use function mb_substr;
use function preg_match;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function time;
use function trim;
use function ucwords;

/**
 * Narrows the loose `$panel->data['identity']` payload into the typed {@see UserIdentityView}.
 *
 * Concentrates every closure that previously lived inline in `_identity.php` (display-value stripping, sensitive-key
 * detection, timestamp detection + humanization, status mapping, attribute bucketing) in one testable place, so the
 * view template is reduced to pure rendering.
 */
final class UserDataNormalizer
{
    /**
     * Identity attribute keys that surface on the hero header and must NOT be repeated in the sections below.
     *
     * @var list<string>
     */
    private const array HERO_KEYS = ['id', 'username', 'name', 'email', 'status'];

    /**
     * Status numeric → display-label / CSS-variant map.
     *
     * Values not in the map fall back to the raw status with the `muted` variant, or to `Unknown` / `muted` for an
     * empty value.
     */
    private const array STATUS_MAP = [
        '10' => ['label' => 'Active', 'variant' => 'success'],
        '9' => ['label' => 'Banned', 'variant' => 'danger'],
        '0' => ['label' => 'Inactive', 'variant' => 'muted'],
    ];

    /**
     * Builds the typed identity view-model from the raw attribute map and the optional label map.
     *
     * @param array<string, string> $identity Raw identity attribute map.
     * @param array<int, array{attribute: string, label: string}>|null $attributes Optional label map from the user
     * panel, or `null` when no labels were supplied.
     */
    public static function fromIdentity(array $identity, array|null $attributes): UserIdentityView
    {
        $labels = self::buildLabelLookup($attributes);

        return new UserIdentityView(
            hero: self::buildHero($identity),
            sections: self::buildSections($identity, $labels),
        );
    }

    /**
     * Builds the typed attribute rows for one section bucket, applying the kind-specific formatting.
     *
     * @param array<string, string> $bucket Attribute key/value pairs to render in the section.
     * @param array<string, string> $labels Label lookup keyed by attribute name.
     * @param string $kind Rendering kind to apply (one of the {@see UserAttribute} `KIND_*` constants).
     *
     * @return list<UserAttribute> Attribute rows in source order.
     */
    private static function buildAttributes(array $bucket, array $labels, string $kind): array
    {
        $rows = [];

        foreach ($bucket as $key => $value) {
            $display = self::stripQuotes($value);

            $isEmpty = $display === '' || $value === 'null';

            if ($isEmpty) {
                $rows[] = new UserAttribute(
                    key: $key,
                    label: self::labelFor($key, $labels),
                    displayValue: '',
                    kind: UserAttribute::KIND_EMPTY,
                );

                continue;
            }

            if ($kind === UserAttribute::KIND_TIMESTAMP) {
                [$rel, $abs] = self::humanTime($value);

                $rows[] = new UserAttribute(
                    key: $key,
                    label: self::labelFor($key, $labels),
                    displayValue: $display,
                    kind: UserAttribute::KIND_TIMESTAMP,
                    timestampRel: $rel,
                    timestampAbs: $abs,
                );

                continue;
            }

            $rows[] = new UserAttribute(
                key: $key,
                label: self::labelFor($key, $labels),
                displayValue: $display,
                kind: $kind,
            );
        }

        return $rows;
    }

    /**
     * Builds the hero view-model from the identity attributes.
     *
     * @param array<string, string> $identity Raw identity attribute map.
     */
    private static function buildHero(array $identity): UserIdentityHero
    {
        $username = self::stripQuotes($identity['username'] ?? $identity['name'] ?? '');
        $email = self::stripQuotes($identity['email'] ?? '');
        $idValue = self::stripQuotes($identity['id'] ?? '');

        $rawStatus = $identity['status'] ?? '';

        [$statusLabel, $statusVariant] = $rawStatus !== ''
            ? self::resolveStatus($rawStatus)
            : ['', 'muted'];

        $monogramSource = $username !== '' ? $username : ($email !== '' ? $email : '?');

        $monogram = mb_strtoupper(mb_substr($monogramSource, 0, 1));

        return new UserIdentityHero(
            username: $username !== '' ? $username : 'Unknown user',
            email: $email,
            idValue: $idValue,
            monogram: $monogram,
            statusLabel: $statusLabel,
            statusVariant: $statusVariant,
        );
    }

    /**
     * Flattens the panel-supplied label map into a `attribute => label` lookup.
     *
     * @param array<int, array{attribute: string, label: string}>|null $attributes Label map, or `null` when absent.
     *
     * @return array<string, string> Label lookup keyed by attribute name.
     */
    private static function buildLabelLookup(array|null $attributes): array
    {
        if (!is_array($attributes)) {
            return [];
        }

        $labels = [];

        foreach ($attributes as $entry) {
            $labels[$entry['attribute']] = $entry['label'];
        }

        return $labels;
    }

    /**
     * Buckets the identity attributes into the four canonical sections (Identity, Security, Timestamps, Other) and
     * produces the matching view-models, dropping empty buckets.
     *
     * @param array<string, string> $identity Raw identity attribute map.
     * @param array<string, string> $labels Label lookup keyed by attribute name.
     *
     * @return list<UserIdentitySection> Non-empty sections in display order.
     */
    private static function buildSections(array $identity, array $labels): array
    {
        $buckets = ['identity' => [], 'security' => [], 'timestamps' => [], 'other' => []];

        foreach (['id', 'username', 'name', 'email'] as $key) {
            if (isset($identity[$key])) {
                $buckets['identity'][$key] = $identity[$key];
            }
        }

        foreach ($identity as $key => $value) {
            if (in_array($key, self::HERO_KEYS, true)) {
                continue;
            }

            if (self::isSensitive($key)) {
                $buckets['security'][$key] = $value;
            } elseif (self::isTimestamp($key, $value)) {
                $buckets['timestamps'][$key] = $value;
            } else {
                $buckets['other'][$key] = $value;
            }
        }

        $sectionConfig = [
            'identity' => [
                'label' => 'Identity',
                'icon' => 'identity',
                'kind' => UserAttribute::KIND_PLAIN,
            ],
            'security' => [
                'label' => 'Security',
                'icon' => 'security',
                'kind' => UserAttribute::KIND_SECURITY,
            ],
            'timestamps' => [
                'label' => 'Timestamps',
                'icon' => 'clock',
                'kind' => UserAttribute::KIND_TIMESTAMP,
            ],
            'other' => [
                'label' => 'Other attributes',
                'icon' => 'dots',
                'kind' => UserAttribute::KIND_PLAIN,
            ],
        ];

        $sections = [];

        foreach ($sectionConfig as $key => $meta) {
            if ($buckets[$key] === []) {
                continue;
            }

            $sections[] = new UserIdentitySection(
                label: $meta['label'],
                icon: Icon::render($meta['icon']),
                attributes: self::buildAttributes($buckets[$key], $labels, $meta['kind']),
            );
        }

        return $sections;
    }

    /**
     * Converts a Unix-epoch string into the `[relative, absolute]` display pair shown in the timestamps section.
     *
     * @param string $value Unix-epoch second, optionally surrounded by `VarDumper` single quotes.
     *
     * @return array{0: string, 1: string} `[relative, absolute]` display strings.
     */
    private static function humanTime(string $value): array
    {
        $unix = (int) trim($value, "'");

        if ($unix <= 0) {
            return ['—', '0'];
        }

        $diff = time() - $unix;
        $absolute = date('M j, Y · H:i', $unix);

        if ($diff < 60) {
            $relative = 'just now';
        } elseif ($diff < 3600) {
            $relative = floor($diff / 60) . ' min ago';
        } elseif ($diff < 86400) {
            $relative = floor($diff / 3600) . ' h ago';
        } elseif ($diff < 2592000) {
            $relative = floor($diff / 86400) . ' d ago';
        } else {
            $relative = $absolute;
        }

        return [$relative, $absolute];
    }

    /**
     * Returns whether the attribute name looks like a secret (auth key, password, token, secret, hash, or salt).
     */
    private static function isSensitive(string $key): bool
    {
        return preg_match('/auth[_\-]?key|password|token|secret|hash|salt/i', $key) === 1;
    }

    /**
     * Returns whether the attribute looks like a timestamp, either by name suffix/prefix or by a 10-digit Unix-epoch
     * value.
     */
    private static function isTimestamp(string $key, string $value): bool
    {
        if (preg_match('/_at$|_time$|^(?:created|updated|deleted|signed_up|last_login)/i', $key) === 1) {
            return true;
        }

        $unquoted = trim($value, "'");

        return ctype_digit($unquoted) && strlen($unquoted) === 10;
    }

    /**
     * Returns the label for `$key`: the configured one when present, otherwise a title-cased version of `$key` with
     * underscores and dots turned into spaces.
     *
     * @param array<string, string> $labels Label lookup keyed by attribute name.
     */
    private static function labelFor(string $key, array $labels): string
    {
        if (isset($labels[$key])) {
            return $labels[$key];
        }

        return ucwords(str_replace(['_', '.'], ' ', $key));
    }

    /**
     * Resolves the status display label and CSS variant for the raw status value.
     *
     * @return array{0: string, 1: string} `[label, variant]`.
     */
    private static function resolveStatus(string $value): array
    {
        $raw = trim($value, "'");

        if (isset(self::STATUS_MAP[$raw])) {
            return [self::STATUS_MAP[$raw]['label'], self::STATUS_MAP[$raw]['variant']];
        }

        return [$raw === '' ? 'Unknown' : $raw, 'muted'];
    }

    /**
     * Strips the single-quote wrapping that {@see \yii\helpers\VarDumper::dumpAsString()} adds around string values.
     *
     * Collapses literal `null` to `''`, so the renderer can treat both forms uniformly.
     */
    private static function stripQuotes(string $value): string
    {
        if ($value === 'null' || $value === '') {
            return '';
        }

        if (str_starts_with($value, "'") && str_ends_with($value, "'") && strlen($value) > 1) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
