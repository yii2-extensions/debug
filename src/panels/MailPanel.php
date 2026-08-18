<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Panel\Mail\{MailMessage, MailSnapshot};
use Throwable;
use Yii;
use yii\debug\{LogTarget, Panel};
use yii\debug\models\search\MailSearch;
use yii\helpers\Url;

use function count;
use function is_string;

/**
 * Renders the mail messages captured by the Mail collector.
 *
 * Presents each dispatched message's metadata (sender, recipients, subject, headers, charset, time) as mail cards;
 * data acquisition and `.eml` persistence live in {@see \yii\debug\collectors\MailCollector}.
 */
class MailPanel extends Panel
{
    protected const string ICON = 'mail';
    protected const string NAME = 'Mail';

    private MailSnapshot|null $snapshot = null;

    /**
     * Renders the detail view with the mail card list.
     */
    #[Override]
    public function getDetail(): string
    {
        $searchModel = new MailSearch();

        $dataProvider = $searchModel->search(Yii::$app->request->get(), $this->getMessages());

        return Yii::$app->view->render(
            'panels/mail/detail',
            [
                'dataProvider' => $dataProvider,
                'panel' => $this,
                'searchModel' => $searchModel,
            ],
            $this,
        );
    }

    /**
     * @return list<MailMessage> Captured mail messages in send order.
     */
    public function getMessages(): array
    {
        return $this->snapshot?->entries() ?? [];
    }

    /**
     * @param array<string, mixed> $payload
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
                    'Sent in the previous request (%s %s) — open it.',
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
        $url = $summary->url;

        $shortUrlPath = $url === '' ? null : parse_url($url, PHP_URL_PATH);
        $shortUrl = is_string($shortUrlPath) && $shortUrlPath !== '' ? $shortUrlPath : $url;

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
}
