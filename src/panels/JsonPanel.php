<?php

declare(strict_types=1);

namespace yii\debug\panels;

use JsonException;
use Override;
use yii\debug\Panel;
use yii\helpers\Html;

use function json_encode;
use function str_replace;
use function trim;
use function ucwords;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Renders stored payloads that do not have a registered Yii2 presentation panel.
 */
final class JsonPanel extends Panel
{
    protected const string ICON = 'dump';

    /**
     * @var array<string, mixed> Stored panel payload.
     */
    private array $payload = [];

    /**
     * Returns the stored payload as escaped, formatted JSON.
     *
     * @throws JsonException When the stored payload cannot be encoded.
     *
     * @return string Escaped JSON detail markup.
     */
    #[Override]
    public function getDetail(): string
    {
        $json = json_encode(
            $this->payload,
            JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return Html::tag('pre', Html::tag('code', Html::encode($json)));
    }

    /**
     * Returns a readable name derived from the stable panel ID.
     *
     * @return string Derived panel name.
     */
    #[Override]
    public function getName(): string
    {
        $name = ucwords(str_replace(['_', '.', '-'], ' ', trim($this->id, '_')));

        return $name === '' ? 'Panel' : $name;
    }

    /**
     * Stores the decoded snapshot payload for safe JSON rendering.
     *
     * @param array<string, mixed> $payload Stored panel payload.
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->payload = $payload;
    }
}
