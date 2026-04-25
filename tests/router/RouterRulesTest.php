<?php

declare(strict_types=1);

namespace yiiunit\debug\router;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\models\router\RouterRules;
use yii\web\UrlRule;
use yiiunit\debug\TestCase;

/**
 * Unit tests for {@see RouterRules} covering URL-manager flag detection (pretty URLs, strict
 * parsing, suffix) and the rule-table flattening for plain, REST, and group URL rules.
 *
 * {@see RouterRulesTest::webRulesProvider} for rule-table scan test case data providers.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 2.1.29
 */
#[Group('router')]
final class RouterRulesTest extends TestCase
{
    public function testDetectsGlobalSuffix(): void
    {
        $this->mockWebApplication(['components' => ['urlManager' => ['suffix' => 'test']]]);

        $router = new RouterRules();

        self::assertFalse($router->prettyUrl, 'prettyUrl must remain false when only suffix is configured.');
        self::assertFalse($router->strictParsing, 'strictParsing must remain false when only suffix is configured.');
        self::assertSame('test', $router->suffix, 'Configured suffix must be exposed verbatim.');
    }

    public function testDetectsPrettyUrlDisabled(): void
    {
        $this->mockWebApplication(['components' => ['urlManager' => ['enablePrettyUrl' => false]]]);

        $router = new RouterRules();

        self::assertFalse($router->prettyUrl, 'enablePrettyUrl=false must surface as prettyUrl false.');
        self::assertFalse($router->strictParsing, 'strictParsing must remain false when only prettyUrl is configured.');
        self::assertNull($router->suffix, 'suffix must remain null when only prettyUrl is configured.');
    }
    public function testDetectsPrettyUrlEnabled(): void
    {
        $this->mockWebApplication(['components' => ['urlManager' => ['enablePrettyUrl' => true]]]);

        $router = new RouterRules();

        self::assertTrue($router->prettyUrl, 'enablePrettyUrl=true must surface as prettyUrl true.');
        self::assertFalse($router->strictParsing, 'strictParsing must default to false when not configured.');
        self::assertNull($router->suffix, 'suffix must default to null when not configured.');
    }

    public function testDetectsStrictParsingDisabled(): void
    {
        $this->mockWebApplication(['components' => ['urlManager' => ['enableStrictParsing' => false]]]);

        $router = new RouterRules();

        self::assertFalse($router->prettyUrl, 'prettyUrl must remain false in the absence of pretty URL config.');
        self::assertFalse($router->strictParsing, 'enableStrictParsing=false must surface as strictParsing false.');
        self::assertNull($router->suffix, 'suffix must remain null when not configured.');
    }

    public function testDetectsStrictParsingEnabled(): void
    {
        $this->mockWebApplication(['components' => ['urlManager' => ['enableStrictParsing' => true]]]);

        $router = new RouterRules();

        self::assertFalse($router->prettyUrl, 'prettyUrl must remain false when only strictParsing is configured.');
        self::assertTrue($router->strictParsing, 'enableStrictParsing=true must surface as strictParsing true.');
        self::assertNull($router->suffix, 'suffix must remain null when only strictParsing is configured.');
    }

    /**
     * @param array<int|string, mixed> $rules
     * @param array<int, array<string, mixed>> $expected
     */
    #[DataProvider('webRulesProvider')]
    public function testFlattensWebRulesIntoStructuredTable(array $rules, array $expected): void
    {
        $this->mockWebApplication([
            'components' => [
                'urlManager' => ['enablePrettyUrl' => true, 'rules' => $rules],
            ],
        ]);

        self::assertSame(
            $expected,
            (new RouterRules())->rules,
            'RouterRules must flatten URL-manager rules into the documented per-row structure.',
        );
    }

    /**
     * @return array<string, array{0: array<int|string, mixed>, 1: array<int, array<string, mixed>>}>
     */
    public static function webRulesProvider(): array
    {
        return [
            'simple' => [
                ['rule' => 'route'],
                [['name' => 'rule', 'route' => 'route', 'verb' => null, 'suffix' => null, 'mode' => null, 'type' => null]],
            ],
            'simple verb' => [
                ['GET rule' => 'route'],
                [['name' => 'rule', 'route' => 'route', 'verb' => ['GET'], 'suffix' => null, 'mode' => null, 'type' => null]],
            ],
            'simple verb parse' => [
                ['POST rule' => 'route'],
                [['name' => 'rule', 'route' => 'route', 'verb' => ['POST'], 'suffix' => null, 'mode' => null, 'type' => null]],
            ],
            'custom' => [
                [['class' => CustomRuleStub::class]],
                [['name' => CustomRuleStub::class, 'route' => null, 'verb' => null, 'suffix' => null, 'mode' => null, 'type' => null]],
            ],
            'creation only' => [
                [['pattern' => 'pattern', 'route' => 'route', 'mode' => UrlRule::CREATION_ONLY]],
                [['name' => 'pattern', 'route' => 'route', 'verb' => null, 'suffix' => null, 'mode' => 'creation only', 'type' => null]],
            ],
            'unknown mode' => [
                [['pattern' => 'pattern', 'route' => 'route', 'mode' => 999]],
                [['name' => 'pattern', 'route' => 'route', 'verb' => null, 'suffix' => null, 'mode' => 'unknown', 'type' => null]],
            ],
            'suffix' => [
                [['pattern' => 'pattern', 'route' => 'route', 'suffix' => '.html']],
                [['name' => 'pattern', 'route' => 'route', 'verb' => null, 'suffix' => '.html', 'mode' => null, 'type' => null]],
            ],
            'group' => [
                [[
                    'class' => 'yii\web\GroupUrlRule',
                    'prefix' => 'admin',
                    'rules' => ['login' => 'user/login', 'logout' => 'user/logout'],
                ]],
                [
                    ['name' => 'admin/login', 'route' => 'admin/user/login', 'verb' => null, 'suffix' => null, 'mode' => null, 'type' => 'GROUP'],
                    ['name' => 'admin/logout', 'route' => 'admin/user/logout', 'verb' => null, 'suffix' => null, 'mode' => null, 'type' => 'GROUP'],
                ],
            ],
            'rest' => [
                [['class' => 'yii\rest\UrlRule', 'controller' => 'user']],
                [
                    ['name' => 'users/<id:\d[\d,]*>', 'route' => 'user/update', 'verb' => ['PUT', 'PATCH'], 'suffix' => null, 'mode' => null, 'type' => 'REST'],
                    ['name' => 'users/<id:\d[\d,]*>', 'route' => 'user/delete', 'verb' => ['DELETE'], 'suffix' => null, 'mode' => null, 'type' => 'REST'],
                    ['name' => 'users/<id:\d[\d,]*>', 'route' => 'user/view', 'verb' => ['GET', 'HEAD'], 'suffix' => null, 'mode' => null, 'type' => 'REST'],
                    ['name' => 'users', 'route' => 'user/create', 'verb' => ['POST'], 'suffix' => null, 'mode' => null, 'type' => 'REST'],
                    ['name' => 'users', 'route' => 'user/index', 'verb' => ['GET', 'HEAD'], 'suffix' => null, 'mode' => null, 'type' => 'REST'],
                    ['name' => 'users/<id:\d[\d,]*>', 'route' => 'user/options', 'verb' => [], 'suffix' => null, 'mode' => null, 'type' => 'REST'],
                    ['name' => 'users', 'route' => 'user/options', 'verb' => [], 'suffix' => null, 'mode' => null, 'type' => 'REST'],
                ],
            ],
        ];
    }
}
