<?php

declare(strict_types=1);

namespace yii\debug\models\router;

use ReflectionClass;
use ReflectionException;
use Yii;
use yii\base\Model;
use yii\rest\UrlRule as RestUrlRule;
use yii\web\GroupUrlRule;
use yii\web\UrlManager;
use yii\web\UrlRule as WebUrlRule;

use function get_class;

/**
 * RouterRules model
 */
class RouterRules extends Model
{
    /**
     * @var bool whether a pretty URL option has been enabled in UrlManager.
     */
    public bool $prettyUrl = false;
    /**
     * @var bool whether a strict parsing option has been enabled in UrlManager.
     */
    public bool $strictParsing = false;
    /**
     * @var string|null global suffix set in UrlManager.
     */
    public string|null $suffix;
    /**
     * @var array logged rules.
     * ```php
     * [
     *  [
     *      'name' => rule name or its class (string),
     *      'route' => (string),
     *      'verb' => (array),
     *      'suffix' => (string),
     *      'mode' => 'parsing only', 'creation only', or null,
     *      'type' => 'REST', 'GROUP', or null,
     *  ]
     * ]
     * ```
     */
    public array $rules = [];

    /**
     * {@inheritdoc}
     *
     * @throws ReflectionException
     */
    public function init(): void
    {
        parent::init();

        if (Yii::$app->urlManager instanceof UrlManager) {
            $this->prettyUrl = Yii::$app->urlManager->enablePrettyUrl;
            $this->suffix = Yii::$app->urlManager->suffix;
            $this->strictParsing = Yii::$app->urlManager->enableStrictParsing;

            if ($this->prettyUrl) {
                foreach (Yii::$app->urlManager->rules as $rule) {
                    $this->scanRule($rule);
                }
            }
        }
    }

    /**
     * Scans rule for basic data.
     *
     * @throws ReflectionException if the class does not exist.
     */
    protected function scanRule($rule, $type = null): void
    {
        $route = $verb = $suffix = $mode = null;

        if ($rule instanceof GroupUrlRule) {
            $this->scanGroupRule($rule);
        } elseif ($rule instanceof RestUrlRule) {
            $this->scanRestRule($rule);
        } else {
            if ($rule instanceof WebUrlRule) {
                $mode = match ($rule->mode) {
                    WebUrlRule::PARSING_ONLY => 'parsing only',
                    WebUrlRule::CREATION_ONLY => 'creation only',
                    null => null,
                    default => 'unknown',
                };

                $name = $rule->name;
                $route = $rule->route;
                $verb = $rule->verb;
                $suffix = $rule->suffix;
            } else {
                $name = get_class($rule);
            }

            $this->rules[] = [
                'name' => $name,
                'route' => $route,
                'verb' => $verb,
                'suffix' => $suffix,
                'mode' => $mode,
                'type' => $type,
            ];
        }
    }

    /**
     * Scans group rule's rules for basic data.
     *
     * @throws ReflectionException
     */
    protected function scanGroupRule(GroupUrlRule $groupRule): void
    {
        foreach ($groupRule->rules as $rule) {
            $this->scanRule($rule, 'GROUP');
        }
    }

    /**
     * Scans REST rule's rules for basic data.
     *
     * @throws ReflectionException
     */
    protected function scanRestRule(RestUrlRule $restRule): void
    {
        $reflectionClass = new ReflectionClass($restRule);
        $reflectionProperty = $reflectionClass->getProperty('rules');
        $rulesGroups = $reflectionProperty->getValue($restRule);

        foreach ($rulesGroups as $rules) {
            foreach ($rules as $rule) {
                $this->scanRule($rule, 'REST');
            }
        }
    }
}
