<?php

declare(strict_types=1);

namespace yii\debug\tests\router;

use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use ReflectionMethod;
use Xepozz\InternalMocker\MockerState;
use Yii;
use yii\debug\models\router\RouterRules;
use yii\debug\tests\provider\RouterRulesProvider;
use yii\debug\tests\support\TestCase;
use yii\rest\UrlRule as RestUrlRule;
use yii\web\UrlRule as WebUrlRule;

/**
 * Unit tests for {@see RouterRules} covering URL-manager flag detection (pretty URLs, strict parsing, suffix) and the
 * rule-table flattening for plain, REST, and group URL rules.
 *
 * {@see RouterRulesProvider} for test case data providers.
 */
#[Group('router')]
final class RouterRulesTest extends TestCase
{
    public function testDetectsGlobalSuffix(): void
    {
        $this->mockWebApplication(
            [
                'components' => ['urlManager' => ['suffix' => 'test']],
            ],
        );

        $router = new RouterRules();

        self::assertFalse(
            $router->prettyUrl,
            "'prettyUrl' must remain false when only suffix is configured.",
        );
        self::assertFalse(
            $router->strictParsing,
            "'strictParsing' must remain false when only suffix is configured.",
        );
        self::assertSame(
            'test',
            $router->suffix,
            'Configured suffix must be exposed verbatim.',
        );
    }

    public function testDetectsPrettyUrlDisabled(): void
    {
        $this->mockWebApplication(
            [
                'components' => ['urlManager' => ['enablePrettyUrl' => false]],
            ],
        );

        $router = new RouterRules();

        self::assertFalse(
            $router->prettyUrl,
            "'enablePrettyUrl=false' must surface as prettyUrl false.",
        );
        self::assertFalse(
            $router->strictParsing,
            "'strictParsing' must remain false when only 'prettyUrl' is configured.",
        );
        self::assertNull(
            $router->suffix,
            "'suffix' must remain null when only 'prettyUrl' is configured.",
        );
    }

    public function testDetectsPrettyUrlEnabled(): void
    {
        $this->mockWebApplication(
            [
                'components' => ['urlManager' => ['enablePrettyUrl' => true]],
            ],
        );

        $router = new RouterRules();

        self::assertTrue(
            $router->prettyUrl,
            "'enablePrettyUrl=true' must surface as prettyUrl 'true'.",
        );
        self::assertFalse(
            $router->strictParsing,
            "'strictParsing' must default to false when not configured.",
        );
        self::assertNull(
            $router->suffix,
            "'suffix' must default to null when not configured.",
        );
    }

    public function testDetectsStrictParsingDisabled(): void
    {
        $this->mockWebApplication(
            [
                'components' => ['urlManager' => ['enableStrictParsing' => false]],
            ],
        );

        $router = new RouterRules();

        self::assertFalse(
            $router->prettyUrl,
            "'prettyUrl' must remain false in the absence of pretty URL config.",
        );
        self::assertFalse(
            $router->strictParsing,
            "'enableStrictParsing=false' must surface as strictParsing 'false'.",
        );
        self::assertNull(
            $router->suffix,
            "'suffix' must remain null when not configured.",
        );
    }

    public function testDetectsStrictParsingEnabled(): void
    {
        $this->mockWebApplication(
            [
                'components' => ['urlManager' => ['enableStrictParsing' => true]],
            ],
        );

        $router = new RouterRules();

        self::assertFalse(
            $router->prettyUrl,
            "'prettyUrl' must remain false when only 'strictParsing' is configured.",
        );
        self::assertTrue(
            $router->strictParsing,
            "'enableStrictParsing=true' must surface as 'strictParsing true'.",
        );
        self::assertNull(
            $router->suffix,
            "'suffix' must remain null when only 'strictParsing' is configured.",
        );
    }

    public function testExtensionMethodsRemainProtected(): void
    {
        self::assertSame(
            [true, true, true],
            array_map(
                static fn(string $method): bool => (new ReflectionMethod(RouterRules::class, $method))->isProtected(),
                ['scanGroupRule', 'scanRestRule', 'scanRule'],
            ),
        );
    }

    /**
     * @param array<int|string, mixed> $rules
     * @param array<int, array<string, mixed>> $expected
     */
    #[DataProviderExternal(RouterRulesProvider::class, 'webRulesCases')]
    public function testFlattensWebRulesIntoStructuredTable(array $rules, array $expected): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'urlManager' => ['enablePrettyUrl' => true, 'rules' => $rules],
                ],
            ],
        );

        self::assertSame(
            $expected,
            (new RouterRules())->rules,
            'RouterRules must flatten URL-manager rules into the documented per-row structure.',
        );
    }

    public function testScanRestRuleContinuesAfterANonIterableInnerGroup(): void
    {
        $this->mockWebApplication();

        $router = new RouterRules();
        $restRule = new RestUrlRule(['controller' => 'user']);

        $this->setInaccessibleProperty(
            $restRule,
            'rules',
            [null, [new WebUrlRule(['pattern' => 'later', 'route' => 'site/later'])]],
        );

        $this->invoke($router, 'scanRestRule', [$restRule]);

        self::assertCount(
            1,
            $router->rules,
            'A malformed inner group must not stop scanning later REST rules.',
        );
        self::assertSame(
            'site/later',
            $router->rules[0]['route'] ?? null,
            'The later valid rule must be captured.',
        );
    }

    public function testScanRestRuleShortCircuitsWhenRulesGroupsArentIterable(): void
    {
        MockerState::addCondition(
            'yii\debug\models\router',
            'is_iterable',
            [],
            false,
            true,
        );

        $this->mockWebApplication(
            [
                'components' => [
                    'urlManager' => [
                        'enablePrettyUrl' => true,
                        'rules' => [['class' => 'yii\\rest\\UrlRule', 'controller' => 'user']],
                    ],
                ],
            ],
        );

        self::assertSame(
            [],
            (new RouterRules())->rules,
            "Non-iterable REST 'rules' groups must short-circuit 'scanRestRule()' and leave the rule list empty.",
        );
    }

    public function testScanRestRuleSkipsNonIterableInnerGroups(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'urlManager' => [
                        'enablePrettyUrl' => true,
                        'rules' => [['class' => 'yii\\rest\\UrlRule', 'controller' => 'user']],
                    ],
                ],
            ],
        );

        $rules = Yii::$app->urlManager->rules;

        self::assertArrayHasKey(
            0,
            $rules,
            'REST rule fixture must surface in the URL manager.',
        );

        $restRule = $rules[0];

        self::assertIsObject(
            $restRule,
            'REST rule must be an object instance.',
        );

        $rulesGroups = $this->getInaccessibleProperty($restRule, 'rules');

        MockerState::addCondition(
            'yii\debug\models\router',
            'is_iterable',
            [],
            false,
            true,
        );
        MockerState::addCondition(
            'yii\debug\models\router',
            'is_iterable',
            [$rulesGroups],
            true,
        );

        self::assertSame(
            [],
            (new RouterRules())->rules,
            "Non-iterable inner groups must be skipped via the defensive 'continue' and leave the rule list empty.",
        );
    }
}
