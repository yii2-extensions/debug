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
 * Snapshots every URL rule registered on the application's URL manager.
 *
 * Recursively unwraps {@see GroupUrlRule} and {@see RestUrlRule} containers so the flat {@see $rules} list reflects the
 * effective routing table exposed to the request.
 */
class RouterRules extends Model
{
    /**
     * Whether `enablePrettyUrl` is enabled on the URL manager.
     */
    public bool $prettyUrl = false;
    /**
     * @var array<int, array<string, mixed>> Flattened URL rules.
     *
     * Each entry carries:
     * - `name` — rule name, or rule class when no name is configured (`string`).
     * - `route` — target route (`string`).
     * - `verb` — HTTP verbs (`array`).
     * - `suffix` — per-rule suffix (`string`).
     * - `mode` — `'parsing only'`, `'creation only'`, or `null`.
     * - `type` — `'REST'`, `'GROUP'`, or `null`.
     */
    public array $rules = [];
    /**
     * Whether `enableStrictParsing` is enabled on the URL manager.
     */
    public bool $strictParsing = false;
    /**
     * Global URL suffix configured on the URL manager.
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
     * Scans each nested rule of a group rule, tagging discovered entries as `'GROUP'`.
     *
     * @param GroupUrlRule $groupRule Group rule whose children should be flattened.
     *
     * @throws ReflectionException When reflection is needed for a nested REST rule and fails.
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
     * Scans the inner rule groups of a REST rule via reflection, tagging discovered entries as `'REST'`.
     *
     * Reads the protected `rules` property because {@see RestUrlRule} does not expose it directly.
     *
     * @throws ReflectionException When the `rules` property cannot be read.
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
     * Records a single rule's summary in {@see $rules}, recursing into group and REST rule containers.
     *
     * @param object $rule Rule instance to summarize.
     * @param string|null $type Origin tag, typically `'REST'`, `'GROUP'`, or `null` for top-level rules.
     *
     * @throws ReflectionException When reflection over a REST rule's inner `rules` property fails.
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
