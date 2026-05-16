<?php

declare(strict_types=1);

namespace yii\debug\widgets;

use UIAwesome\Html\Palpable\A;
use yii\base\Widget;
use yii\debug\Panel;
use yii\helpers\Url;

use function array_filter;
use function array_keys;
use function array_search;
use function end;
use function implode;
use function is_int;
use function reset;

/**
 * Render button for navigation to previous or next request in debug panel.
 */
class NavigationButton extends Widget
{
    public const string BUTTON_NEXT = 'Next';
    public const string BUTTON_PREV = 'Prev';

    /**
     * Button label and behavior selector (`'Prev'` / `'Next'`).
     */
    public string $button = '';
    /**
     * Manifest of captured requests (newest first); tag => summary.
     *
     * @var array<string, mixed>
     */
    public array $manifest = [];
    /**
     * Active panel, used to compose the target URL.
     */
    public Panel|null $panel = null;
    /**
     * Active request tag.
     */
    public string $tag = '';
    /**
     * Zero-based index of `$tag` inside `array_keys($manifest)`; `-1` when the tag isn't present.
     */
    private int $currentTagIndex = -1;
    /**
     * First manifest tag (newest captured); empty when the manifest is empty.
     */
    private string $firstTag = '';
    /**
     * Last manifest tag (oldest captured); empty when the manifest is empty.
     */
    private string $lastTag = '';

    public function beforeRun(): bool
    {
        $manifestKeys = array_keys($this->manifest);
        $first = reset($manifestKeys);

        $this->firstTag = $first === false ? '' : $first;

        $last = end($manifestKeys);

        $this->lastTag = $last === false ? '' : $last;

        $cursorIndex = array_search($this->tag, $manifestKeys, true);
        $this->currentTagIndex = is_int($cursorIndex) ? $cursorIndex : -1;

        return parent::beforeRun();
    }

    public function run(): string
    {
        return match ($this->button) {
            self::BUTTON_NEXT => $this->renderNextButton(),
            self::BUTTON_PREV => $this->renderPrevButton(),
            default => '',
        };
    }

    private function buildClass(bool $needLink): string
    {
        return implode(
            ' ',
            array_filter(['yii-debug-btn', 'yii-debug-btn-ghost', $needLink ? '' : 'is-disabled']),
        );
    }

    /**
     * @return array<int|string, string>|string Empty string when there is no neighbour at the requested direction.
     */
    private function getRoute(int $inc): array|string
    {
        if ($this->panel === null || $this->currentTagIndex < 0) {
            return '';
        }

        $manifestKeys = array_keys($this->manifest);
        $targetIndex = $this->currentTagIndex + $inc;

        if (!isset($manifestKeys[$targetIndex])) {
            return '';
        }

        return [
            'view',
            'panel' => $this->panel->id,
            'tag' => $manifestKeys[$targetIndex],
        ];
    }

    private function renderNextButton(): string
    {
        $needLink = $this->tag !== $this->lastTag;

        return A::tag()
            ->class($this->buildClass($needLink))
            ->href(Url::to($needLink ? $this->getRoute(1) : ''))
            ->content('Next')
            ->render();
    }

    private function renderPrevButton(): string
    {
        $needLink = $this->tag !== $this->firstTag;

        return A::tag()
            ->class($this->buildClass($needLink))
            ->href(Url::to($needLink ? $this->getRoute(-1) : ''))
            ->content('Prev')
            ->render();
    }
}
