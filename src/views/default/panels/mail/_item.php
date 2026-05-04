<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/** @var array<string, mixed> $model */
/** @var int $index */

$asString = static fn(mixed $v): string
    => is_scalar($v) || $v instanceof Stringable ? (string) $v : '';

$splitAddresses = static function (string $s): array {
    if ($s === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $s)), static fn(string $a): bool => $a !== ''));
};

// Pick a deterministic hue for the avatar — same sender always gets the same color.
$hueFor = static function (string $email): int {
    if ($email === '') {
        return 210;
    }
    return abs(crc32(strtolower($email))) % 360;
};

$initialsFor = static function (string $email): string {
    if ($email === '') {
        return '?';
    }
    $local = explode('@', $email)[0] ?? '';
    $seed = $local !== '' ? $local : $email;
    return mb_strtoupper(mb_substr($seed, 0, 1));
};

$humanTime = static function (mixed $time): array {
    if ($time instanceof DateTimeInterface) {
        $unix = $time->getTimestamp();
    } elseif (is_int($time)) {
        $unix = $time;
    } elseif (is_string($time) && $time !== '') {
        $parsed = strtotime($time);
        if ($parsed === false) {
            return ['', ''];
        }
        $unix = $parsed;
    } else {
        return ['', ''];
    }

    $absolute = date('M j, Y · H:i:s', $unix);
    $diff = time() - $unix;

    if ($diff < 0) {
        $relative = 'just now';
    } elseif ($diff < 60) {
        $relative = 'just now';
    } elseif ($diff < 3600) {
        $relative = (int) floor($diff / 60) . ' min ago';
    } elseif ($diff < 86400) {
        $relative = (int) floor($diff / 3600) . ' h ago';
    } elseif ($diff < 2592000) {
        $relative = (int) floor($diff / 86400) . ' d ago';
    } else {
        $relative = $absolute;
    }

    return [$relative, $absolute];
};

$from         = $asString($model['from'] ?? '');
$toList       = $splitAddresses($asString($model['to'] ?? ''));
$ccList       = $splitAddresses($asString($model['cc'] ?? ''));
$bccList      = $splitAddresses($asString($model['bcc'] ?? ''));
$replyList    = $splitAddresses($asString($model['reply'] ?? ''));
$subject      = $asString($model['subject'] ?? '');
$body         = $asString($model['body'] ?? '');
$headers      = $asString($model['headers'] ?? '');
$charset      = $asString($model['charset'] ?? '');
$file         = isset($model['file']) && is_string($model['file']) ? $model['file'] : '';
$isSuccessful = ($model['isSuccessful'] ?? false) === true;

[$relTime, $absTime] = $humanTime($model['time'] ?? null);

$collapsedBody = $body === '' ? '' : (string) preg_replace('/\s+/', ' ', $body);
$bodyPreview = $collapsedBody === '' ? '' : mb_substr($collapsedBody, 0, 140);
if ($bodyPreview !== '' && mb_strlen($collapsedBody) > 140) {
    $bodyPreview .= '…';
}

$recipientGroups = [
    'to' => ['label' => 'TO',       'list' => $toList],
    'cc' => ['label' => 'CC',       'list' => $ccList],
    'bcc' => ['label' => 'BCC',     'list' => $bccList],
    'reply' => ['label' => 'REPLY-TO', 'list' => $replyList],
];

$hasRecipients = false;
foreach ($recipientGroups as $g) {
    if ($g['list'] !== []) {
        $hasRecipients = true;
        break;
    }
}
?>
<article class="yii-debug-mail-card">
    <header class="yii-debug-mail-card-head">
        <span
            class="yii-debug-mail-avatar"
            style="--mail-hue: <?= $hueFor($from) ?>"
            aria-hidden="true"
        ><?= Html::encode($initialsFor($from)) ?></span>

        <div class="yii-debug-mail-headline">
            <p class="yii-debug-mail-from">
                <?= Html::encode($from !== '' ? $from : '(no sender)') ?>
            </p>
            <h2 class="yii-debug-mail-subject">
                <?= Html::encode($subject !== '' ? $subject : '(no subject)') ?>
            </h2>
            <?php if ($bodyPreview !== ''): ?>
                <p class="yii-debug-mail-preview"><?= Html::encode($bodyPreview) ?></p>
            <?php endif; ?>
        </div>

        <div class="yii-debug-mail-meta">
            <?php if ($isSuccessful): ?>
                <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success">
                    <span class="yii-debug-mail-status-dot" aria-hidden="true"></span>
                    Sent
                </span>
            <?php else: ?>
                <span class="yii-debug-mail-status yii-debug-mail-status-fail" title="Mailer reported failure">
                    <span class="yii-debug-mail-status-dot" aria-hidden="true"></span>
                    Failed
                </span>
            <?php endif; ?>

            <?php if ($relTime !== ''): ?>
                <time class="yii-debug-mail-time" title="<?= Html::encode($absTime) ?>">
                    <?= Html::encode($relTime) ?>
                </time>
            <?php endif; ?>

            <?php if ($file !== ''): ?>
                <a
                    class="yii-debug-mail-download"
                    href="<?= Html::encode(Url::to(['download-mail', 'file' => $file])) ?>"
                    title="Download .eml"
                    aria-label="Download .eml"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 4v12"/>
                        <path d="M7 11l5 5 5-5"/>
                        <path d="M5 20h14"/>
                    </svg>
                </a>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($hasRecipients): ?>
        <div class="yii-debug-mail-recipients">
            <?php foreach ($recipientGroups as $key => $group): ?>
                <?php if ($group['list'] === []) {
                    continue;
                } ?>
                <div class="yii-debug-mail-recipient-group">
                    <span class="yii-debug-mail-recipient-label" data-role="<?= Html::encode($key) ?>">
                        <?= Html::encode($group['label']) ?>
                    </span>
                    <span class="yii-debug-mail-recipient-pills">
                        <?php foreach ($group['list'] as $email): ?>
                            <span class="yii-debug-mail-recipient-pill" title="<?= Html::encode($email) ?>">
                                <?= Html::encode($email) ?>
                            </span>
                        <?php endforeach; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($body !== ''): ?>
        <div class="yii-debug-mail-body"><?= Html::encode($body) ?></div>
    <?php else: ?>
        <div class="yii-debug-mail-body yii-debug-mail-body-empty">
            (empty body)
        </div>
    <?php endif; ?>

    <?php if ($headers !== '' || $charset !== ''): ?>
        <details class="yii-debug-mail-tech">
            <summary>
                <span class="yii-debug-mail-tech-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 6L4 12l5 6"/>
                        <path d="M15 6l5 6-5 6"/>
                    </svg>
                </span>
                <span class="yii-debug-mail-tech-label">Raw headers</span>
                <?php if ($charset !== ''): ?>
                    <span class="yii-debug-mail-tech-charset" title="Charset">
                        <?= Html::encode($charset) ?>
                    </span>
                <?php endif; ?>
                <span class="yii-debug-mail-tech-chevron" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 9l6 6 6-6"/>
                    </svg>
                </span>
            </summary>
            <pre class="yii-debug-mail-headers"><?= Html::encode($headers) ?></pre>
        </details>
    <?php endif; ?>
</article>
