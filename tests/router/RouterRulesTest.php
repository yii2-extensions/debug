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
                [['mode' => null, 'name' => 'rule', 'route' => 'route', 'suffix' => null, 'type' => null, 'verb' => null]],
            ],
            'simple verb' => [
                ['GET rule' => 'route'],
                [['mode' => null, 'name' => 'rule', 'route' => 'route', 'suffix' => null, 'type' => null, 'verb' => ['GET']]],
            ],
            'simple verb parse' => [
                ['POST rule' => 'route'],
                [['mode' => null, 'name' => 'rule', 'route' => 'route', 'suffix' => null, 'type' => null, 'verb' => ['POST']]],
            ],
            'custom' => [
                [['class' => CustomRuleStub::class]],
                [['mode' => null, 'name' => CustomRuleStub::class, 'route' => null, 'suffix' => null, 'type' => null, 'verb' => null]],
            ],
            'creation only' => [
                [['pattern' => 'pattern', 'route' => 'route', 'mode' => UrlRule::CREATION_ONLY]],
                [['mode' => 'creation only', 'name' => 'pattern', 'route' => 'route', 'suffix' => null, 'type' => null, 'verb' => null]],
            ],
            'unknown mode' => [
                [['pattern' => 'pattern', 'route' => 'route', 'mode' => 999]],
                [['mode' => 'unknown', 'name' => 'pattern', 'route' => 'route', 'suffix' => null, 'type' => null, 'verb' => null]],
            ],
            'suffix' => [
                [['pattern' => 'pattern', 'route' => 'route', 'suffix' => '.html']],
                [['mode' => null, 'name' => 'pattern', 'route' => 'route', 'suffix' => '.html', 'type' => null, 'verb' => null]],
            ],
            'group' => [
                [[
                    'class' => 'yii\web\GroupUrlRule',
                    'prefix' => 'admin',
                    'rules' => ['login' => 'user/login', 'logout' => 'user/logout'],
                ]],
                [
                    ['mode' => null, 'name' => 'admin/login', 'route' => 'admin/user/login', 'suffix' => null, 'type' => 'GROUP', 'verb' => null],
                    ['mode' => null, 'name' => 'admin/logout', 'route' => 'admin/user/logout', 'suffix' => null, 'type' => 'GROUP', 'verb' => null],
                ],
            ],
            'rest' => [
                [['class' => 'yii\rest\UrlRule', 'controller' => 'user']],
                [
                    ['mode' => null, 'name' => 'users/<id:\d[\d,]*>', 'route' => 'user/update', 'suffix' => null, 'type' => 'REST', 'verb' => ['PUT', 'PATCH']],
                    ['mode' => null, 'name' => 'users/<id:\d[\d,]*>', 'route' => 'user/delete', 'suffix' => null, 'type' => 'REST', 'verb' => ['DELETE']],
                    ['mode' => null, 'name' => 'users/<id:\d[\d,]*>', 'route' => 'user/view', 'suffix' => null, 'type' => 'REST', 'verb' => ['GET', 'HEAD']],
                    ['mode' => null, 'name' => 'users', 'route' => 'user/create', 'suffix' => null, 'type' => 'REST', 'verb' => ['POST']],
                    ['mode' => null, 'name' => 'users', 'route' => 'user/index', 'suffix' => null, 'type' => 'REST', 'verb' => ['GET', 'HEAD']],
                    ['mode' => null, 'name' => 'users/<id:\d[\d,]*>', 'route' => 'user/options', 'suffix' => null, 'type' => 'REST', 'verb' => []],
                    ['mode' => null, 'name' => 'users', 'route' => 'user/options', 'suffix' => null, 'type' => 'REST', 'verb' => []],
                ],
            ],
        ];
    }
}
