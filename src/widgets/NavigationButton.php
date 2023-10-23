<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\widgets;

use yii\base\Widget;
use yii\debug\Panel;
use yii\helpers\Html;

use function array_keys;
use function array_search;
use function end;
use function reset;

/**
 * Render button for navigation to previous or next request in debug panel.
 *
 * @since 2.0.11
 */
class NavigationButton extends Widget
{
    public array $manifest = [];

    public string $tag = '';

    public string $button = '';
    /**
     * @var Panel|null
     */
    public Panel $panel;


    private string $firstTag = '';

    private string $lastTag = '';

    private int $currentTagIndex = 0;

    public function beforeRun(): bool
    {
        $manifestKeys = array_keys($this->manifest);
        $this->firstTag = reset($manifestKeys);
        $this->lastTag = end($manifestKeys);
        $this->currentTagIndex = array_search($this->tag, $manifestKeys);

        return parent::beforeRun();
    }

    public function run()
    {
        $method = "render{$this->button}Button";

        return $this->$method();
    }

    private function renderPrevButton(): string
    {
        $needLink = $this->tag !== $this->firstTag;

        return Html::a(
            'Prev',
            $needLink ? $this->getRoute(-1) : '',
            ['class' => ['btn', 'btn-light', $needLink ? '' : 'disabled']]
        );
    }

    private function renderNextButton(): string
    {
        $needLink = $this->tag !== $this->lastTag;

        return Html::a(
            'Next',
            $needLink ? $this->getRoute(1) : '',
            ['class' => ['btn', 'btn-light', $needLink ? '' : 'disabled']]
        );
    }

    /**
     * @param int $inc Direction
     */
    private function getRoute(int $inc): array
    {
        return [
            'view',
            'panel' => $this->panel?->id,
            'tag' => array_keys($this->manifest)[$this->currentTagIndex + $inc],
        ];
    }
}
