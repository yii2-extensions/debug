<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Panel\Mail\{MailEntry, MailMessage, MailPanel as MailPresenter, MailSnapshot};
use PHPForge\Debug\Panel\{PanelIcon, PanelRenderer, PanelTitle};
use PHPForge\Debug\Storage\HydrationException;
use Throwable;
use Yii;
use yii\debug\{LogTarget, Panel};
use yii\helpers\Url;

use function count;
use function is_string;

/**
 * Renders the mail messages captured by the Mail collector.
 *
 * Delegates the detail view to the framework-neutral {@see MailPresenter}; data acquisition and `.eml` persistence
 * live in {@see \yii\debug\collectors\MailCollector}.
 */
class MailPanel extends Panel
{
    /**
     * Captured payload hydrated by {@see hydrate()}, or `null` before hydration.
     */
    private MailSnapshot|null $snapshot = null;

    /**
     * Renders the detail view through the shared declarative presenter.
     *
     * @return string Rendered panel markup.
     */
    #[Override]
    public function getDetail(): string
    {
        $view = (new MailPresenter())->present($this->snapshot?->jsonSerialize() ?? ['entries' => []]);

        return PanelRenderer::render($this->getName(), $view);
    }

    /**
     * Returns the mail messages dispatched during the request.
     *
     * @return list<MailEntry> Captured mail messages in send order.
     */
    public function getMessages(): array
    {
        return $this->snapshot?->entries() ?? [];
    }

    /**
     * Returns the panel display name from the shared title enum.
     *
     * @return string Panel display name.
     */
    #[Override]
    public function getName(): string
    {
        return PanelTitle::MAIL->value;
    }

    /**
     * Returns the icon key from the shared panel icon enum.
     *
     * @return string Toolbar icon key.
     */
    #[Override]
    public function getToolbarIcon(): string
    {
        return PanelIcon::MAIL->value;
    }

    /**
     * Decodes the captured payload into the typed mail snapshot backing this panel.
     *
     * @param array<string, mixed> $payload Captured panel payload.
     *
     * @throws HydrationException when the payload does not match the snapshot schema.
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->snapshot = MailSnapshot::fromArray($payload, "$.panels.{$this->id}");
    }

    /**
     * Builds the toolbar items.
     *
     * Returns the captured count when the current request sent at least one message; otherwise looks at the previous
     * captured request and surfaces a `cross-request` chip pointing at its panel when it carries mail (handles the
     * Post-Redirect-Get flow where the mail was sent by the request before the redirect).
     *
     * @return array<int, array<string, mixed>> Toolbar items, or `[]` when neither the current nor the previous
     * request captured any mail.
     */
    #[Override]
    protected function getToolbarItems(): array
    {
        if ($this->snapshot === null) {
            return [
                [
                    'status' => 'warning',
                    'value' => '!',
                ],
            ];
        }

        $mailCount = count($this->getMessages());

        if ($mailCount > 0) {
            return [['value' => $mailCount]];
        }

        $previous = $this->findPreviousRequestWithMail();

        if ($previous === null) {
            return [];
        }

        return [
            [
                'value' => $previous['count'],
                'status' => 'cross-request',
                'title' => sprintf(
                    MailMessage::PREVIOUS_REQUEST->value,
                    $previous['method'],
                    $previous['shortUrl'],
                ),
                'url' => $previous['url'],
            ],
        ];
    }

    /**
     * Looks at the debug manifest for the request immediately preceding the current one and returns its mail count
     * when non-zero, falling back to the most-recent manifest entry when the current tag is not yet listed (race
     * during the very first response of a session).
     *
     * @return array{count: int, method: string, shortUrl: string, url: string}|null Cross-request chip payload, or
     * `null` when no usable previous request exists.
     */
    private function findPreviousRequestWithMail(): array|null
    {
        $module = $this->module;

        if ($module === null) {
            return null;
        }

        $logTarget = $module->logTarget;

        if (!$logTarget instanceof LogTarget) {
            return null;
        }

        try {
            $manifest = $logTarget->loadManifest();
        } catch (Throwable) {
            return null;
        }

        $currentTag = $this->tag;

        $previousTag = null;
        $summary = null;
        $found = false;

        foreach ($manifest as $tag => $entry) {
            if ($found) {
                $previousTag = $tag;
                $summary = $entry;

                break;
            }

            if ($tag === $currentTag) {
                $found = true;

                continue;
            }

            // Newest entry that is not the current request, used when no entry follows the current tag.
            $previousTag ??= $tag;
            $summary ??= $entry;
        }

        if ($summary === null || $previousTag === null) {
            return null;
        }

        $count = $summary->mailCount;

        if ($count === 0) {
            return null;
        }

        $method = $summary->method;

        $urlManager = Yii::$app->getUrlManager();

        $shortUrl = self::shortUrl($summary->url, $urlManager->enablePrettyUrl ? null : $urlManager->routeParam);

        $moduleId = $module->getUniqueId();

        $panelUrl = Url::toRoute(
            [
                "/{$moduleId}/view",
                'panel' => $this->id,
                'tag' => $previousTag,
            ],
        );

        return [
            'count' => $count,
            'method' => $method,
            'shortUrl' => $shortUrl,
            'url' => $panelUrl,
        ];
    }

    /**
     * Returns the part of a captured URL the cross-request tooltip names.
     *
     * Without pretty URLs the route travels in the query string (`/index.php?r=site%2Fcontact`), so the decoded route
     * is named instead of the entry script; otherwise the path is named, or the URL itself when it has no path.
     *
     * @param string $url Captured request URL.
     * @param string|null $routeParam Query parameter that carries the route, or `null` when the URL manager routes from
     * the path (pretty URLs) and a query parameter with that name is plain request data.
     *
     * @return string Route, path, or the unchanged URL.
     */
    private static function shortUrl(string $url, string|null $routeParam): string
    {
        if ($routeParam !== null) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            $route = $query[$routeParam] ?? null;

            if (is_string($route) && $route !== '') {
                return $route;
            }
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : $url;
    }
}
