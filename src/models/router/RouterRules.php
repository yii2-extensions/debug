<?php

declare(strict_types=1);

namespace yii\debug\models\router;

use ReflectionException;
use Yii;
use yii\base\Model;
use yii\rest\UrlRule as RestUrlRule;
use yii\web\GroupUrlRule;
use yii\web\UrlRule as WebUrlRule;

use function is_object;

/**
 * Collects information about URL rules used in the application.
 */
class RouterRules extends Model
{
    /**
     * Whether pretty URL option has been enabled in UrlManager
     */
    public bool $prettyUrl = false;
    /**
     * @var array<int, array<string, mixed>> Logged rules.
     *
     * Each entry has the following keys:
     * - `name` => rule name or its class (string)
     * - `route` => (string)
     * - `verb` => (array)
     * - `suffix` => (string)
     * - `mode` => 'parsing only', 'creation only', or null
     * - `type` => 'REST', 'GROUP', or null
     */
    public array $rules = [];
    /**
     * Whether strict parsing option has been enabled in UrlManager
     */
    public bool $strictParsing = false;
    /**
     * Global suffix set in UrlManager
     */
    public string|null $suffix = null;

    public function init(): void
    {
        parent::init();

        $urlManager = Yii::$app->urlManager;

        $this->prettyUrl = $urlManager->enablePrettyUrl;
        $this->suffix = $urlManager->suffix;
        $this->strictParsing = $urlManager->enableStrictParsing;

        if ($this->prettyUrl) {
            foreach ($urlManager->rules as $rule) {
                if (is_object($rule)) {
                    $this->scanRule($rule);
                }
            }
        }
    }

    /**
     * Scans group rule's rules for basic data.
     *
     * @param GroupUrlRule $groupRule Group rule to scan.
     *
     * @throws ReflectionException if the rules property is not accessible or does not exist.
     */
    protected function scanGroupRule(GroupUrlRule $groupRule): void
    {
        foreach ($groupRule->rules as $rule) {
            if (is_object($rule)) {
                $this->scanRule($rule, 'GROUP');
            }
        }
    }

    /**
     * Scans REST rule's rules for basic data.
     *
     * @throws ReflectionException if the rules property is not accessible or does not exist.
     */
    protected function scanRestRule(RestUrlRule $restRule): void
    {
        $reflectionClass = new \ReflectionClass($restRule);

        $reflectionProperty = $reflectionClass->getProperty('rules');
        $rulesGroups = $reflectionProperty->getValue($restRule);

        if (!is_iterable($rulesGroups)) {
            return;
        }

        foreach ($rulesGroups as $rules) {
            if (!is_iterable($rules)) {
                continue;
            }

            foreach ($rules as $rule) {
                if (is_object($rule)) {
                    $this->scanRule($rule, 'REST');
                }
            }
        }
    }

    /**
     * Scans rule for basic data.
     *
     * @param object $rule Rule to scan.
     * @param string|null $type Rule type (for example, 'REST', 'GROUP
     *
     * @throws ReflectionException if the rules property is not accessible or does not exist.
     */
    protected function scanRule(object $rule, string|null $type = null): void
    {
        $route = $verb = $suffix = $mode = null;

        if ($rule instanceof GroupUrlRule) {
            $this->scanGroupRule($rule);
        } elseif ($rule instanceof RestUrlRule) {
            $this->scanRestRule($rule);
        } else {
            if ($rule instanceof WebUrlRule) {
                switch ($rule->mode) {
                    case WebUrlRule::PARSING_ONLY:
                        $mode = 'parsing only';
                        break;
                    case WebUrlRule::CREATION_ONLY:
                        $mode = 'creation only';
                        break;
                    case null:
                        $mode = null;
                        break;
                    default:
                        $mode = 'unknown';
                }

                $name = $rule->name;
                $route = $rule->route;
                $verb = $rule->verb;
                $suffix = $rule->suffix;
            } else {
                $name = get_class($rule);
            }

            $this->rules[] = [
                'mode' => $mode,
                'name' => $name,
                'route' => $route,
                'suffix' => $suffix,
                'type' => $type,
                'verb' => $verb,
            ];
        }
    }
}
