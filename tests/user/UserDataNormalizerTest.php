<?php

declare(strict_types=1);

namespace yii\debug\tests\user;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\user\{UserAttribute, UserDataNormalizer};
use yii\debug\tests\support\TestCase;

use function array_map;

/**
 * Unit tests for {@see UserDataNormalizer} covering the narrowing of the loose `$panel->data['identity']` payload
 * into the typed view-model: hero composition (monogram + status variant), attribute bucketing (Identity / Security /
 * Timestamps / Other), VarDumper-quote stripping, sensitive-key detection and timestamp humanization.
 */
#[Group('panel')]
#[Group('user')]
final class UserDataNormalizerTest extends TestCase
{
    public function testFromIdentityBucketsAttributesIntoOtherSectionWhenNotSensitiveNotTimestamp(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            [
                'username' => "'admin'",
                'preferred_locale' => "'en'",
                'tier' => "'gold'",
            ],
            null,
        );

        $other = null;

        foreach ($view->sections as $section) {
            if ($section->label === 'Other attributes') {
                $other = $section;
            }
        }

        self::assertNotNull(
            $other,
            "Plain non-sensitive non-timestamp keys must surface under the 'Other attributes' section.",
        );

        $keys = array_map(static fn(UserAttribute $a): string => $a->key, $other->attributes);

        self::assertContains(
            'preferred_locale',
            $keys,
            "'preferred_locale' must land in the Other bucket.",
        );
        self::assertContains(
            'tier',
            $keys,
            "'tier' must land in the Other bucket.",
        );
    }
    public function testFromIdentityBucketsSensitiveAttributesIntoSecuritySection(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            [
                'id' => "'1'",
                'username' => "'admin'",
                'auth_key' => "'abc'",
                'password_hash' => "'def'",
                'verification_token' => "'tok'",
            ],
            null,
        );

        $captions = [];

        foreach ($view->sections as $section) {
            $captions[] = $section->label;
        }

        self::assertContains(
            'Security',
            $captions,
            'Sensitive keys must surface in the Security section.',
        );

        foreach ($view->sections as $candidate) {
            if ($candidate->label === 'Security') {
                $keys = array_map(static fn(UserAttribute $a): string => $a->key, $candidate->attributes);

                self::assertSame(
                    ['auth_key', 'password_hash', 'verification_token'],
                    $keys,
                    'Security bucket must hold every sensitive attribute.',
                );

                foreach ($candidate->attributes as $attr) {
                    self::assertSame(
                        UserAttribute::KIND_SECURITY,
                        $attr->kind,
                        'Security rows must carry the security kind.',
                    );
                }
            }
        }
    }

    public function testFromIdentityBuildsAvatarMonogramFromUsername(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            ['username' => "'admin'"],
            null,
        );

        self::assertSame(
            'A',
            $view->hero->monogram,
            'Monogram must come from the first letter of the username.',
        );
        self::assertSame(
            'admin',
            $view->hero->username,
            'Username must surface with VarDumper quotes stripped.',
        );
    }

    public function testFromIdentityFallsBackMonogramToEmailWhenUsernameMissing(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            ['email' => "'someone@example.com'"],
            null,
        );

        self::assertSame(
            'S',
            $view->hero->monogram,
            'Monogram must fall back to the email first letter.',
        );
        self::assertSame(
            'Unknown user',
            $view->hero->username,
            'Missing username must yield the `Unknown user` placeholder.',
        );
    }

    public function testFromIdentityHumanizesTimestampsAcrossEveryRelativeBucket(): void
    {
        $now = time();

        $view = UserDataNormalizer::fromIdentity(
            [
                'just_now_at' => "'" . ($now - 5) . "'",
                'minute_ago_at' => "'" . ($now - 600) . "'",
                'hour_ago_at' => "'" . ($now - 7200) . "'",
                'day_ago_at' => "'" . ($now - 172800) . "'",
                'old_at' => "'0'",
            ],
            null,
        );

        $relatives = [];

        foreach ($view->sections as $section) {
            if ($section->label !== 'Timestamps') {
                continue;
            }

            foreach ($section->attributes as $attr) {
                $relatives[$attr->key] = $attr->timestampRel;
            }
        }

        self::assertSame(
            'just now',
            $relatives['just_now_at'] ?? null,
            "Timestamps within the last minute must render as 'just now'.",
        );
        self::assertStringEndsWith(
            ' min ago',
            $relatives['minute_ago_at'] ?? '',
            "Timestamps under an hour must render as '<n> min ago'.",
        );
        self::assertStringEndsWith(
            ' h ago',
            $relatives['hour_ago_at'] ?? '',
            "Timestamps under a day must render as '<n> h ago'.",
        );
        self::assertStringEndsWith(
            ' d ago',
            $relatives['day_ago_at'] ?? '',
            "Timestamps under a month must render as '<n> d ago'.",
        );
        self::assertSame(
            '—',
            $relatives['old_at'] ?? null,
            'Zero / invalid timestamps must surface as the em-dash sentinel.',
        );
    }

    public function testFromIdentityMapsActiveStatusToSuccessVariant(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            ['username' => "'a'", 'status' => '10'],
            null,
        );

        self::assertSame(
            'Active',
            $view->hero->statusLabel,
            "'10' must map to the 'Active' label.",
        );
        self::assertSame(
            'success',
            $view->hero->statusVariant,
            "'10' must map to the 'success' CSS variant.",
        );
    }

    public function testFromIdentityMapsBannedStatusToDangerVariant(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            ['username' => "'a'", 'status' => '9'],
            null,
        );

        self::assertSame(
            'Banned',
            $view->hero->statusLabel,
            "'9' must map to the 'Banned' label.",
        );
        self::assertSame(
            'danger',
            $view->hero->statusVariant,
            "'9' must map to the 'danger' CSS variant.",
        );
    }

    public function testFromIdentityMapsTimestampsIntoTimestampsSection(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            [
                'id' => "'1'",
                'created_at' => '1640000000',
                'updated_at' => '1740000000',
            ],
            null,
        );

        $timestampsSection = null;

        foreach ($view->sections as $section) {
            if ($section->label === 'Timestamps') {
                $timestampsSection = $section;
            }
        }

        self::assertNotNull(
            $timestampsSection,
            "Timestamps section must surface when '_at' keys exist.",
        );
        self::assertCount(
            2,
            $timestampsSection->attributes,
            'Both timestamp rows must land in the bucket.',
        );

        foreach ($timestampsSection->attributes as $attr) {
            self::assertSame(
                UserAttribute::KIND_TIMESTAMP,
                $attr->kind,
                'Timestamp rows must carry the timestamp kind.',
            );
            self::assertNotSame(
                '',
                $attr->timestampAbs,
                'Absolute timestamp must be populated.',
            );
        }
    }

    public function testFromIdentityMapsUnknownStatusToRawValue(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            ['username' => "'a'", 'status' => "'pending'"],
            null,
        );

        self::assertSame(
            'pending',
            $view->hero->statusLabel,
            'Unknown status must show the raw value verbatim.',
        );
        self::assertSame(
            'muted',
            $view->hero->statusVariant,
            'Unknown status must fall back to the muted variant.',
        );
    }

    public function testFromIdentityResolvesAttributeLabelsFromTheLabelMap(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            [
                'id' => "'1'",
                'username' => "'admin'",
            ],
            [
                ['attribute' => 'id', 'label' => 'User ID'],
                ['attribute' => 'username', 'label' => 'Login'],
            ],
        );

        self::assertNotEmpty(
            $view->sections,
            'Identity section must be present.',
        );

        $identitySection = $view->sections[0];

        $labels = array_map(static fn(UserAttribute $a): string => $a->label, $identitySection->attributes);

        self::assertContains(
            'User ID',
            $labels,
            "Custom label map must override the default 'Id' title-case label.",
        );
        self::assertContains(
            'Login',
            $labels,
            "Custom label map must override the default 'Username' title-case label.",
        );
    }

    public function testFromIdentitySkipsEmptyValuesAsEmptyKind(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            [
                'username' => "'admin'",
                'password_reset_token' => 'null',
            ],
            null,
        );

        $securityAttr = null;

        foreach ($view->sections as $section) {
            if ($section->label === 'Security' && $section->attributes !== []) {
                $securityAttr = $section->attributes[0];
            }
        }

        self::assertNotNull(
            $securityAttr,
            'Security section must surface even when value is empty.',
        );
        self::assertSame(
            UserAttribute::KIND_EMPTY,
            $securityAttr->kind,
            '`null` value must collapse to the empty kind.',
        );
        self::assertSame(
            '',
            $securityAttr->displayValue,
            'Empty kind must carry an empty display value.',
        );
    }

    public function testFromIdentityStripsSingleQuotesFromDisplayValue(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            ['username' => "'admin'"],
            null,
        );

        self::assertSame(
            'admin',
            $view->hero->username,
            'VarDumper single-quote wrapping must be stripped.',
        );
    }
}
