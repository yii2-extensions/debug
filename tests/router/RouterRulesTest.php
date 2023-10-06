<?php

declare(strict_types=1);

namespace yiiunit\debug\router;

use yii\debug\models\router\RouterRules;
use yiiunit\debug\TestCase;

class RouterRulesTest extends TestCase
{
    /**
     * @test
     */
    public function shouldDetectPrettyUrlEnabled(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'urlManager' => [
                        'enablePrettyUrl' => true,
                    ],
                ],
            ]
        );

        $router = new RouterRules();
        $this->assertTrue($router->prettyUrl);
        $this->assertFalse($router->strictParsing);
        $this->assertNull($router->suffix);
    }

    /**
     * @test
     */
    public function shouldDetectPrettyUrlDisabled(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'urlManager' => [
                        'enablePrettyUrl' => false,
                    ],
                ],
            ]
        );

        $router = new RouterRules();
        $this->assertFalse($router->prettyUrl);
        $this->assertFalse($router->strictParsing);
        $this->assertNull($router->suffix);
    }

    /**
     * @test
     */
    public function shouldDetectStrictParsingEnabled(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'urlManager' => [
                        'enableStrictParsing' => true,
                    ],
                ],
            ]
        );

        $router = new RouterRules();
        $this->assertFalse($router->prettyUrl);
        $this->assertTrue($router->strictParsing);
        $this->assertNull($router->suffix);
    }

    /**
     * @test
     */
    public function shouldDetectStrictParsingDisabled(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'urlManager' => [
                        'enableStrictParsing' => false,
                    ],
                ],
            ]
        );

        $router = new RouterRules();
        $this->assertFalse($router->prettyUrl);
        $this->assertFalse($router->strictParsing);
        $this->assertNull($router->suffix);
    }

    /**
     * @test
     */
    public function shouldDetectGlobalSuffix(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'urlManager' => [
                        'suffix' => 'test',
                    ],
                ],
            ]
        );

        $router = new RouterRules();
        $this->assertFalse($router->prettyUrl);
        $this->assertFalse($router->strictParsing);
        $this->assertSame('test', $router->suffix);
    }

    /**
     * @test
     *
     * @dataProvider \yiiunit\debug\providers\Data::forWebRules
     *
     * @param array $rules
     * @param array $expected
     */
    public function shouldProperlyScanWebRule($rules, $expected): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'urlManager' => [
                        'enablePrettyUrl' => true,
                        'rules' => $rules,
                    ],
                ],
            ]
        );

        $router = new RouterRules();
        $this->assertSame($expected, $router->rules);
    }
}
