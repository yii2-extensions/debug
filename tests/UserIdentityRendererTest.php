<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\user\{
    UserAttribute,
    UserDataNormalizer,
    UserIdentityHero,
    UserIdentityRenderer,
    UserIdentitySection,
    UserIdentityView,
};

/**
 * Unit tests for {@see UserIdentityRenderer} covering the hero card composition, the per-attribute kind branches
 * (plain / security reveal button / timestamp / empty placeholder) and the section header chips.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('panel')]
#[Group('user')]
final class UserIdentityRendererTest extends TestCase
{
    public function testRenderEmitsTimestampRelativeAndAbsoluteParts(): void
    {
        $view = new UserIdentityView(
            hero: $this->emptyHero(),
            sections: [
                new UserIdentitySection(
                    label: 'Timestamps',
                    icon: '<svg></svg>',
                    attributes: [
                        new UserAttribute(
                            key: 'created_at',
                            label: 'Created At',
                            displayValue: '1640000000',
                            kind: UserAttribute::KIND_TIMESTAMP,
                            timestampRel: '28 d ago',
                            timestampAbs: 'Apr 13, 2026 · 14:19',
                        ),
                    ],
                ),
            ],
        );

        $html = UserIdentityRenderer::render($view);

        self::assertStringContainsString(
            '28 d ago',
            $html,
            'Relative time must surface in the row.',
        );
        self::assertStringContainsString(
            'Apr 13, 2026',
            $html,
            'Absolute time must surface in the row.',
        );
        self::assertStringContainsString(
            'yii-debug-user-time',
            $html,
            'Timestamp rows must carry the time CSS class.',
        );
    }

    public function testRenderEmitsTwoButtonsForSecurityAttributes(): void
    {
        $view = new UserIdentityView(
            hero: $this->emptyHero(),
            sections: [
                new UserIdentitySection(
                    label: 'Security',
                    icon: '<svg></svg>',
                    attributes: [
                        new UserAttribute(
                            key: 'auth_key',
                            label: 'Auth Key',
                            displayValue: 'abc',
                            kind: UserAttribute::KIND_SECURITY,
                        ),
                        new UserAttribute(
                            key: 'password_hash',
                            label: 'Password Hash',
                            displayValue: 'def',
                            kind: UserAttribute::KIND_SECURITY,
                        ),
                    ],
                ),
            ],
        );

        $html = UserIdentityRenderer::render($view);

        self::assertSame(
            2,
            substr_count($html, '<button'),
            'Each security attribute must render its reveal button.',
        );
        self::assertStringContainsString(
            'data-yii-debug-reveal',
            $html,
            'Reveal buttons must carry the JS hook attribute.',
        );
    }

    public function testRenderHeroEmitsAvatarMonogramAndStatusVariant(): void
    {
        $view = new UserIdentityView(
            hero: new UserIdentityHero(
                username: 'admin',
                email: 'admin@example.com',
                idValue: '1',
                monogram: 'A',
                statusLabel: 'Active',
                statusVariant: 'success',
            ),
            sections: [],
        );

        $html = UserIdentityRenderer::render($view);

        self::assertStringContainsString(
            'A</span>',
            $html,
            'Monogram must render inside the avatar span.',
        );
        self::assertStringContainsString(
            'yii-debug-user-status-success',
            $html,
            'Status variant must surface as the CSS modifier.',
        );
        self::assertStringContainsString(
            'admin@example.com',
            $html,
            'Email must surface in the handle paragraph.',
        );
        self::assertStringContainsString(
            'yii-debug-user-handle',
            $html,
            'Handle paragraph must use the handle CSS class.',
        );
        self::assertStringContainsString(
            'ID #1',
            $html,
            'ID pill must surface with the `#` prefix.',
        );
    }

    public function testRenderHeroOmitsEmailWhenMissing(): void
    {
        $view = new UserIdentityView(
            hero: new UserIdentityHero(
                username: 'admin',
                email: '',
                idValue: '',
                monogram: 'A',
                statusLabel: '',
                statusVariant: 'muted',
            ),
            sections: [],
        );

        $html = UserIdentityRenderer::render($view);

        self::assertStringNotContainsString(
            'yii-debug-user-handle',
            $html,
            'Empty email must drop the handle paragraph entirely.',
        );
    }

    public function testRenderHeroOmitsStatusPillWhenLabelEmpty(): void
    {
        $view = new UserIdentityView(
            hero: new UserIdentityHero(
                username: 'admin',
                email: '',
                idValue: '',
                monogram: 'A',
                statusLabel: '',
                statusVariant: 'muted',
            ),
            sections: [],
        );

        $html = UserIdentityRenderer::render($view);

        self::assertStringNotContainsString(
            'yii-debug-user-status',
            $html,
            'Empty status label must drop the status pill.',
        );
    }

    public function testRenderSurfacesEmptyDashForEmptyAttribute(): void
    {
        $view = new UserIdentityView(
            hero: $this->emptyHero(),
            sections: [
                new UserIdentitySection(
                    label: 'Security',
                    icon: '<svg></svg>',
                    attributes: [
                        new UserAttribute(
                            key: 'token',
                            label: 'Token',
                            displayValue: '',
                            kind: UserAttribute::KIND_EMPTY,
                        ),
                    ],
                ),
            ],
        );

        $html = UserIdentityRenderer::render($view);

        self::assertStringContainsString(
            'yii-debug-user-empty',
            $html,
            'Empty rows must surface the dedicated CSS class.',
        );
        self::assertStringContainsString(
            '—',
            $html,
            'Empty rows must show the em-dash placeholder.',
        );
    }

    public function testRenderWiresFullPipelineThroughNormalizer(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            [
                'id' => "'1'",
                'username' => "'admin'",
                'status' => '10',
                'email' => "'admin@example.com'",
                'auth_key' => "'authkey-12345'",
                'created_at' => '1640000000',
            ],
            null,
        );

        $html = UserIdentityRenderer::render($view);

        self::assertStringContainsString(
            'yii-debug-user-name',
            $html,
            'End-to-end view must surface the user-name heading.',
        );
        self::assertStringContainsString(
            'admin',
            $html,
            'End-to-end username must reach the rendered DOM.'
        );
        self::assertStringContainsString(
            'yii-debug-user-status-success',
            $html,
            'End-to-end status variant must reach the DOM.'
        );
        self::assertStringContainsString(
            'data-yii-debug-reveal',
            $html,
            'End-to-end auth_key must trigger the security reveal button.'
        );
        self::assertStringContainsString(
            'yii-debug-user-time',
            $html,
            'End-to-end created_at must trigger the timestamp formatter.'
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockWebApplication();
    }

    protected function tearDown(): void
    {
        $this->destroyApplication();

        parent::tearDown();
    }

    private function emptyHero(): UserIdentityHero
    {
        return new UserIdentityHero(
            username: 'admin',
            email: '',
            idValue: '',
            monogram: 'A',
            statusLabel: '',
            statusVariant: 'muted',
        );
    }
}
