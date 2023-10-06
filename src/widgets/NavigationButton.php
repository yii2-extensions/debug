<?php

declare(strict_types=1);

namespace yii\debug\widgets;

use yii\base\Widget;
use yii\debug\Panel;
use yii\helpers\Html;

use function array_keys;
use function array_search;
use function end;
use function reset;

/**
 * Render button for navigation to previous or next request in a debug panel
 */
class NavigationButton extends Widget
{
    /** @var array */
    public array $manifest;
    /** @var string */
    public string $tag;
    /** @var string */
    public string $button;
    /** @var Panel */
    public Panel $panel;

    /** @var string */
    private string $firstTag;
    /** @var string */
    private string $lastTag;
    /** @var int */
    private int $currentTagIndex;

    /**
     * @inheritDoc
     */
    public function beforeRun(): bool
    {
        $manifestKeys = array_keys($this->manifest);
        $this->firstTag = reset($manifestKeys);
        $this->lastTag = end($manifestKeys);
        $this->currentTagIndex = array_search($this->tag, $manifestKeys);

        return parent::beforeRun();
    }

    /**
     * @inheritDoc
     */
    public function run()
    {
        $method = "render{$this->button}Button";

        return $this->$method();
    }

    /**
     * @return string
     */
    private function renderPrevButton(): string
    {
        $needLink = $this->tag !== $this->firstTag;

        return Html::a(
            'Prev',
            $needLink ? $this->getRoute(-1) : '',
            ['class' => ['btn', 'btn-light', $needLink ? '' : 'disabled']]
        );
    }

    /**
     * @return string
     */
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
     *
     * @return array
     */
    private function getRoute(int $inc): array
    {
        return [
            'view',
            'panel' => $this->panel->id,
            'tag' => array_keys($this->manifest)[$this->currentTagIndex + $inc],
        ];
    }
}
