<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\debug\Panel;

/**
 * Stub trace-line callable recording the arguments {@see Panel::getTraceLine()} forwards to it.
 */
final class TraceLineRenderer
{
    /**
     * @var array<string, mixed> Frame received on the last call.
     */
    public array $frame = [];
    public Panel|null $panel = null;

    /**
     * @param array<string, mixed> $frame
     */
    public function __invoke(array $frame, Panel $panel): string
    {
        return $this->render($frame, $panel);
    }

    /**
     * @param array<string, mixed> $frame
     */
    public function render(array $frame, Panel $panel): string
    {
        $this->frame = $frame;
        $this->panel = $panel;

        return '<a href="app://open?file={file}&line={line}">{text}</a>';
    }
}
