<?php

declare(strict_types=1);

use yii\debug\helpers\Icon;
use yii\helpers\Html;

/** @var \yii\web\View $this */
/** @var array<string, string> $identity Map of attribute name → VarDumper-formatted string. */
/** @var array<int, array{attribute: string, label: string}>|null $attributes */

// Build a [attribute => label] lookup for clean section rendering.
$labels = [];
if (is_array($attributes)) {
    foreach ($attributes as $attr) {
        $labels[$attr['attribute']] = $attr['label'];
    }
}

$labelFor = static function (string $key) use ($labels): string {
    if (isset($labels[$key])) {
        return $labels[$key];
    }
    return ucwords(str_replace(['_', '.'], ' ', $key));
};

// Strip VarDumper's surrounding quotes for nicer display; keep the raw form available.
$display = static function (string $value): string {
    if ($value === 'null' || $value === '') {
        return '';
    }
    if (str_starts_with($value, "'") && str_ends_with($value, "'") && strlen($value) > 1) {
        return substr($value, 1, -1);
    }
    return $value;
};

$isSensitive = static fn(string $key): bool => preg_match(
    '/auth[_\-]?key|password|token|secret|hash|salt/i',
    $key,
) === 1;

$isTimestamp = static function (string $key, string $value): bool {
    if (preg_match('/_at$|_time$|^(?:created|updated|deleted|signed_up|last_login)/i', $key) === 1) {
        return true;
    }
    return ctype_digit(trim($value, "'")) && strlen(trim($value, "'")) === 10;
};

$humanTime = static function (string $value): array {
    $unix = (int) trim($value, "'");
    if ($unix <= 0) {
        return ['—', '0'];
    }
    $diff = time() - $unix;
    $absolute = date('M j, Y · H:i', $unix);
    if ($diff < 60) {
        $relative = 'just now';
    } elseif ($diff < 3600) {
        $relative = floor($diff / 60) . ' min ago';
    } elseif ($diff < 86400) {
        $relative = floor($diff / 3600) . ' h ago';
    } elseif ($diff < 2592000) {
        $relative = floor($diff / 86400) . ' d ago';
    } else {
        $relative = $absolute;
    }
    return [$relative, $absolute];
};

$resolveStatus = static function (string $value): array {
    $raw = trim($value, "'");
    return match ($raw) {
        '10' => ['Active', 'success'],
        '9' => ['Banned', 'danger'],
        '0' => ['Inactive', 'muted'],
        default => [$raw === '' ? 'Unknown' : $raw, 'muted'],
    };
};

// Pull hero fields (with defensive fallbacks).
$username = $display($identity['username'] ?? $identity['name'] ?? '');
$email = $display($identity['email'] ?? '');
$idValue = $display($identity['id'] ?? '');
$rawStatus = $identity['status'] ?? '';
[$statusLabel, $statusVariant] = $rawStatus !== '' ? $resolveStatus($rawStatus) : ['', 'muted'];

// Monogram seed: prefer username, fall back to email local part, then "?".
$monogramSource = $username !== '' ? $username : ($email !== '' ? $email : '?');
$monogram = mb_strtoupper(mb_substr($monogramSource, 0, 1));

// Bucket every remaining attribute into a semantic section. Anything not matched
// lands in "Other" so custom user models still render fully.
$buckets = ['identity' => [], 'security' => [], 'timestamps' => [], 'other' => []];
$heroKeys = ['id', 'username', 'name', 'email', 'status'];

foreach ($identity as $key => $value) {
    if (in_array($key, $heroKeys, true)) {
        continue;
    }
    if ($isSensitive($key)) {
        $buckets['security'][$key] = $value;
    } elseif ($isTimestamp($key, $value)) {
        $buckets['timestamps'][$key] = $value;
    } else {
        $buckets['other'][$key] = $value;
    }
}

// "Identity" section gets a stable slice — keeps the layout predictable across users.
foreach (['id', 'username', 'name', 'email'] as $key) {
    if (isset($identity[$key])) {
        $buckets['identity'][$key] = $identity[$key];
    }
}
?>
<section class="yii-debug-user">
    <header class="yii-debug-user-card">
        <span class="yii-debug-user-avatar" aria-hidden="true"><?= Html::encode($monogram) ?></span>
        <div class="yii-debug-user-meta">
            <h2 class="yii-debug-user-name"><?= Html::encode($username !== '' ? $username : 'Unknown user') ?></h2>
            <?php if ($email !== ''): ?>
                <p class="yii-debug-user-handle"><?= Html::encode($email) ?></p>
            <?php endif; ?>
            <div class="yii-debug-user-tags">
                <?php if ($statusLabel !== ''): ?>
                    <span class="yii-debug-user-status yii-debug-user-status-<?= $statusVariant ?>">
                        <?= Html::encode($statusLabel) ?>
                    </span>
                <?php endif; ?>
                <?php if ($idValue !== ''): ?>
                    <span class="yii-debug-user-pill">ID #<?= Html::encode($idValue) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php
    $sections = [
        'identity' => [
            'label' => 'Identity',
            'icon' => Icon::render('identity'),
        ],
        'security' => [
            'label' => 'Security',
            'icon' => Icon::render('security'),
        ],
        'timestamps' => [
            'label' => 'Timestamps',
            'icon' => Icon::render('clock'),
        ],
        'other' => [
            'label' => 'Other attributes',
            'icon' => Icon::render('dots'),
        ],
    ];
?>

    <?php foreach ($sections as $key => $meta): ?>
        <?php if ($buckets[$key] === []) {
            continue;
        } ?>
        <article class="yii-debug-user-section">
            <header>
                <span class="yii-debug-user-section-icon" aria-hidden="true"><?= $meta['icon'] ?></span>
                <span><?= Html::encode($meta['label']) ?></span>
            </header>
            <dl>
                <?php foreach ($buckets[$key] as $attrKey => $attrValue): ?>
                    <?php
                    $strValue = $attrValue;
                    $rendered = $display($strValue);
                    $isEmpty = $rendered === '' || $strValue === 'null';
                    ?>
                    <div class="yii-debug-user-row">
                        <dt><?= Html::encode($labelFor($attrKey)) ?></dt>
                        <dd>
                            <?php if ($isEmpty): ?>
                                <span class="yii-debug-user-empty">—</span>
                            <?php elseif ($key === 'security'): ?>
                                <button
                                    type="button"
                                    class="yii-debug-user-reveal"
                                    data-yii-debug-reveal
                                    aria-label="Reveal <?= Html::encode($labelFor($attrKey)) ?>"
                                >
                                    <span class="yii-debug-user-mask">••••••••••••</span>
                                    <span class="yii-debug-user-real"><?= Html::encode($rendered) ?></span>
                                    <span class="yii-debug-user-reveal-cta" aria-hidden="true"></span>
                                </button>
                            <?php elseif ($key === 'timestamps'): ?>
                                <?php [$rel, $abs] = $humanTime($strValue); ?>
                                <span class="yii-debug-user-time" title="<?= Html::encode($rendered) ?>">
                                    <span class="yii-debug-user-time-rel"><?= Html::encode($rel) ?></span>
                                    <span class="yii-debug-user-time-abs"><?= Html::encode($abs) ?></span>
                                </span>
                            <?php else: ?>
                                <span class="yii-debug-user-value"><?= Html::encode($rendered) ?></span>
                            <?php endif; ?>
                        </dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </article>
    <?php endforeach; ?>
</section>
