<?php

declare(strict_types=1);

use yii\debug\PhpInfoAsset;
use yii\helpers\Html;

/** @var \yii\web\View $this */

PhpInfoAsset::register($this);

$this->title = 'PHP Info';

ob_start();
phpinfo();
$pinfo = ob_get_contents();
ob_end_clean();

$body = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', (string) $pinfo) ?? '';

// ---------------------------------------------------------------------------
// Split the phpinfo output into the "Overview" chunk (everything before the
// first `<h2>` — the hero PHP version block + the system/build/config table)
// and the "modules" chunk (every `<h2>` + table that follows). The Overview
// gets re-rendered as a grid of info cards; modules keep their tables but
// each gets wrapped in a `<section>` so the search filter and the TOC can
// hide / deep-link them.
// ---------------------------------------------------------------------------

$firstH2Pos = strpos($body, '<h2');
$overviewSrc = $firstH2Pos === false ? $body : substr($body, 0, $firstH2Pos);
$modulesSrc  = $firstH2Pos === false ? '' : substr($body, $firstH2Pos);

// Pull every `<tr><td>k</td><td>v</td></tr>` pair out of the Overview tables.
// Multi-cell rows (like "Registered PHP Streams" with a long values column)
// are flattened to plain strings — rare but harmless, the value column ends
// up containing the raw text either way.
$overviewRows = [];
if (preg_match_all('%<table[^>]*>(.*?)</table>%s', $overviewSrc, $tableMatches)) {
    foreach ($tableMatches[1] as $tableHtml) {
        if (preg_match_all('%<tr[^>]*>\s*<td[^>]*>(.*?)</td>\s*<td[^>]*>(.*?)</td>\s*</tr>%s', $tableHtml, $rowMatches, PREG_SET_ORDER)) {
            foreach ($rowMatches as $row) {
                $key = trim(html_entity_decode(strip_tags($row[1]), ENT_QUOTES, 'UTF-8'));
                $val = trim(html_entity_decode(strip_tags($row[2]), ENT_QUOTES, 'UTF-8'));
                if ($key === '' || stripos($key, 'PHP Logo') !== false) {
                    continue;
                }
                $overviewRows[$key] = $val;
            }
        }
    }
}

$pluck = static function (array $rows, string $key, string $fallback = ''): string {
    return isset($rows[$key]) && $rows[$key] !== '' ? $rows[$key] : $fallback;
};

// Path shortener — collapses the user's home directory to `~` so long paths
// stop bleeding off the row (full path is preserved in the `title` tooltip
// so nothing is hidden, just compacted). Uses every reasonable signal —
// `$_SERVER`, `getenv`, `posix_getpwuid` — because PHP's built-in dev server
// inherits an environment that doesn't always populate `$_SERVER['HOME']`.
$home = '';
foreach (['HOME', 'USERPROFILE'] as $envKey) {
    $candidate = $_SERVER[$envKey] ?? getenv($envKey);
    if (is_string($candidate) && $candidate !== '') {
        $home = rtrim($candidate, '/\\');
        break;
    }
}
if ($home === '' && function_exists('posix_getpwuid') && function_exists('posix_getuid')) {
    $info = @posix_getpwuid(@posix_getuid() ?: 0);
    if (is_array($info) && is_string($info['dir'] ?? null) && $info['dir'] !== '') {
        $home = rtrim($info['dir'], '/\\');
    }
}
$shortenPath = static function (string $path) use ($home): string {
    if ($home === '' || !str_starts_with($path, $home . '/')) {
        return $path;
    }
    return '~' . substr($path, strlen($home));
};

// Smart renderer for a tile's value — handles enabled/disabled pills, path
// lists collapsed to basename tokens, single paths shortened to `~/...`, and
// plain values. Centralized so all hero sub-sections render identically.
$renderTileValue = static function (string $value) use ($shortenPath): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $lower = strtolower($value);
    if (in_array($lower, ['enabled', 'yes'], true)) {
        return '<span class="yii-debug-phpinfo-overview-pill" data-variant="success">' . Html::encode($value) . '</span>';
    }
    if (in_array($lower, ['disabled', 'no'], true)) {
        return '<span class="yii-debug-phpinfo-overview-pill" data-variant="muted">' . Html::encode($value) . '</span>';
    }
    $isPathList = str_contains($value, ',') && (str_starts_with($value, '/') || str_starts_with($value, 'C:'));
    if ($isPathList) {
        $html = '<span class="yii-debug-phpinfo-overview-files">';
        foreach (array_filter(array_map('trim', explode(',', $value))) as $f) {
            $html .= '<code class="yii-debug-phpinfo-overview-token" title="' . Html::encode($f) . '">'
                . Html::encode(basename($f)) . '</code>';
        }
        return $html . '</span>';
    }

    // Short-token comma list — extension names, stream transports, filters, etc.
    // Detected by: every token is short (<= 32 chars), no internal whitespace,
    // and there are 2+ tokens. Picks up `https, ftps, compress.zlib, ...` while
    // leaving multi-word phrases like the Build System string alone (its first
    // segment contains spaces, fails the test, falls through to plain text).
    if (str_contains($value, ',')) {
        $tokens = array_values(array_filter(array_map('trim', explode(',', $value))));
        $isTokenList = count($tokens) > 1;
        foreach ($tokens as $t) {
            if ($t === '' || preg_match('/\s/', $t) || mb_strlen($t) > 32) {
                $isTokenList = false;
                break;
            }
        }
        if ($isTokenList) {
            $html = '<span class="yii-debug-phpinfo-overview-files">';
            foreach ($tokens as $t) {
                $html .= '<code class="yii-debug-phpinfo-overview-token">' . Html::encode($t) . '</code>';
            }
            return $html . '</span>';
        }
    }

    $isPath = str_starts_with($value, '/') || str_starts_with($value, 'C:');
    if ($isPath) {
        return '<code title="' . Html::encode($value) . '">' . Html::encode($shortenPath($value)) . '</code>';
    }
    return '<code>' . Html::encode($value) . '</code>';
};

$memoryLimit = ini_get('memory_limit');
$phpVersion = PHP_VERSION;
$sapi = PHP_SAPI;
$os = php_uname('s') . ' ' . php_uname('r');

// Hero metrics — runtime identity tiles inside the hero block. Build info
// gets its own peer block below the hero (`$buildMetrics`) with the same
// chrome so the two blocks read as a unified pair without crowding 10 tiles
// into one ragged grid.
$heroMetrics = [
    'SAPI' => $sapi,
    'OS' => $os,
    'Server API' => $pluck($overviewRows, 'Server API'),
    'Memory limit' => is_string($memoryLimit) ? $memoryLimit : '',
    'Virtual Dir Support' => $pluck($overviewRows, 'Virtual Directory Support'),
];

$buildMetrics = [
    'Build Date' => $pluck($overviewRows, 'Build Date'),
    'Build System' => $pluck($overviewRows, 'Build System'),
    'PHP API' => $pluck($overviewRows, 'PHP API'),
    'PHP Extension' => $pluck($overviewRows, 'PHP Extension'),
    'Zend Extension' => $pluck($overviewRows, 'Zend Extension'),
];

$configMetrics = [
    'Loaded Configuration File' => $pluck($overviewRows, 'Loaded Configuration File'),
    'Configuration File (php.ini) Path' => $pluck($overviewRows, 'Configuration File (php.ini) Path'),
    'Scan this dir for additional .ini files' => $pluck($overviewRows, 'Scan this dir for additional .ini files'),
    'Additional .ini files parsed' => $pluck($overviewRows, 'Additional .ini files parsed'),
];

$capabilitiesMetrics = [
    'Debug Build' => $pluck($overviewRows, 'Debug Build'),
    'Thread Safety' => $pluck($overviewRows, 'Thread Safety'),
    'Zend Signal Handling' => $pluck($overviewRows, 'Zend Signal Handling'),
    'Zend Memory Manager' => $pluck($overviewRows, 'Zend Memory Manager'),
    'IPv6 Support' => $pluck($overviewRows, 'IPv6 Support'),
    'DTrace Support' => $pluck($overviewRows, 'DTrace Support'),
];

$streamsMetrics = [
    'Registered PHP Streams' => $pluck($overviewRows, 'Registered PHP Streams'),
    'Registered Stream Socket Transports' => $pluck($overviewRows, 'Registered Stream Socket Transports'),
    'Registered Stream Filters' => $pluck($overviewRows, 'Registered Stream Filters'),
];

// `$overviewCards` is now empty — every short-form Overview block lives
// inside the hero shell. Only long-form info (Configure Command, Streams &
// transports) keeps the standalone `<details>` chrome below the hero.
$overviewCards = [];

$configureCommand = $pluck($overviewRows, 'Configure Command');

// ---------------------------------------------------------------------------
// Modules: wrap each `<h2>NAME</h2>` and its trailing table inside a section
// so the search filter and the TOC can hide / deep-link them. The first
// section (the synthetic Overview built above) is opened separately.
// ---------------------------------------------------------------------------

$modulesSrc = str_replace(
    '<table',
    '<div class="yii-debug-table-wrap"><table class="yii-debug-table yii-debug-phpinfo__table" ',
    $modulesSrc,
);
$modulesSrc = str_replace('</table>', '</table></div>', $modulesSrc);

$sections = [['title' => 'Overview', 'slug' => 'phpinfo-overview']];
$slugify = static function (string $title): string {
    $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $title));
    return 'phpinfo-' . trim($slug, '-');
};

$modulesSrc = preg_replace_callback(
    '%<h2[^>]*>(.*?)</h2>%s',
    static function (array $m) use (&$sections, $slugify): string {
        $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
        $slug = $slugify($title);
        $sections[] = ['title' => $title, 'slug' => $slug];
        return '</section><section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="' . Html::encode($slug)
            . '" data-section="' . Html::encode($title) . '">'
            . '<header class="yii-debug-phpinfo-module-head">'
            . '<span class="yii-debug-phpinfo-module-dot" aria-hidden="true"></span>'
            . '<h2>' . Html::encode($title) . '</h2>'
            . '</header>';
    },
    $modulesSrc,
) ?? $modulesSrc;

// `preg_replace_callback` leaves a stray `</section>` at the very front
// because our first `<h2>` callback emits `</section>` before opening its
// own. Strip it so the markup balances out.
$modulesSrc = preg_replace('%^\s*</section>%', '', $modulesSrc) ?? $modulesSrc;
?>
<div class="yii-debug-page">
    <h1 class="yii-debug-hero-title">phpinfo</h1>

    <div class="yii-debug-phpinfo-shell">
        <aside class="yii-debug-phpinfo-toc" aria-label="phpinfo modules">
            <header class="yii-debug-phpinfo-toc-title">
                <span><?= count($sections) ?></span>
                <span>modules</span>
            </header>
            <ul class="yii-debug-phpinfo-toc-list">
                <?php foreach ($sections as $section): ?>
                    <li>
                        <a class="yii-debug-phpinfo-toc-link"
                           href="#<?= Html::encode($section['slug']) ?>"
                           data-toc-target="<?= Html::encode($section['slug']) ?>">
                            <?= Html::encode($section['title']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <div class="yii-debug-phpinfo-main">
            <div class="yii-debug-phpinfo-search">
                <span class="yii-debug-phpinfo-search-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="7"/>
                        <path d="m21 21-4.3-4.3"/>
                    </svg>
                </span>
                <input type="search"
                       class="yii-debug-phpinfo-search-input"
                       placeholder="Filter modules + directives…"
                       autocomplete="off"
                       spellcheck="false"
                       data-yii-debug-phpinfo-search>
                <span class="yii-debug-phpinfo-search-empty" data-yii-debug-phpinfo-empty hidden>
                    No modules match this query.
                </span>
            </div>

            <section class="yii-debug-phpinfo-section" id="phpinfo-overview" data-section="Overview">
                <?php
                // Helper that renders one sub-section (eyebrow + tile grid) so the
                // hero shell hosts PHP version + Build + Configuration with one
                // shared template.
                $renderSection = static function (string $eyebrow, array $tiles, string|null $headline = null) use ($renderTileValue, $phpVersion): string {
                    $hasContent = false;
                    foreach ($tiles as $v) {
                        if (trim((string) $v) !== '') {
                            $hasContent = true;
                            break;
                        }
                    }
                    if (!$hasContent && $headline === null) {
                        return '';
                    }

                    $html = '<section class="yii-debug-phpinfo-overview-hero-section" aria-label="' . Html::encode($eyebrow) . '">'
                        . '<header class="yii-debug-phpinfo-overview-block-head">'
                        . '<span class="yii-debug-phpinfo-overview-block-eyebrow">' . Html::encode($eyebrow) . '</span>'
                        . '</header>';

                    if ($headline !== null) {
                        $html .= '<div class="yii-debug-phpinfo-overview-hero-headline">'
                            . '<strong class="yii-debug-phpinfo-overview-hero-version">' . Html::encode($headline) . '</strong>'
                            . '<span class="yii-debug-phpinfo-overview-hero-mark" aria-hidden="true">php</span>'
                            . '</div>';
                    }

                    $html .= '<dl class="yii-debug-phpinfo-overview-hero-metrics">';
                    foreach ($tiles as $label => $value) {
                        $value = trim((string) $value);
                        if ($value === '') {
                            continue;
                        }
                        $html .= '<div class="yii-debug-phpinfo-overview-hero-metric">'
                            . '<dt>' . Html::encode($label) . '</dt>'
                            . '<dd>' . $renderTileValue($value) . '</dd>'
                            . '</div>';
                    }
                    $html .= '</dl></section>';

                    return $html;
                };
?>

                <div class="yii-debug-phpinfo-overview-hero">
                    <?= $renderSection('PHP version', $heroMetrics, $phpVersion) ?>
                    <?= $renderSection('Build', $buildMetrics) ?>
                    <?= $renderSection('Configuration', $configMetrics) ?>
                    <?= $renderSection('Capabilities', $capabilitiesMetrics) ?>
                    <?= $renderSection('Streams', $streamsMetrics) ?>
                </div>

                <div class="yii-debug-phpinfo-overview-grid">
                    <?php foreach ($overviewCards as $card): ?>
                        <?php
        $hasContent = false;
                        foreach ($card['rows'] as $value) {
                            if (trim((string) $value) !== '') {
                                $hasContent = true;
                                break;
                            }
                        }
                        if (!$hasContent) {
                            continue;
                        }
                        ?>
                        <details class="yii-debug-phpinfo-overview-details">
                            <summary>
                                <span class="yii-debug-phpinfo-overview-details-label"><?= Html::encode($card['label']) ?></span>
                                <span class="yii-debug-phpinfo-overview-details-hint">click to expand</span>
                            </summary>
                            <dl class="yii-debug-phpinfo-overview-card-list">
                                <?php foreach ($card['rows'] as $key => $value): ?>
                                    <?php $value = trim((string) $value); ?>
                                    <?php if ($value === '') {
                                        continue;
                                    } ?>
                                    <?php
                                    $lower = strtolower($value);
                                    $boolHit = in_array($lower, ['enabled', 'disabled', 'yes', 'no'], true);
                                    $variant = match ($lower) {
                                        'enabled', 'yes' => 'success',
                                        'disabled', 'no' => 'muted',
                                        default => null,
                                    };
                                    // Comma-separated list of paths (e.g. "Additional .ini files parsed")
                                    // — render each entry as a token showing only the basename, with the
                                    // full path in `title` so the dev sees "apcu.ini", "oci8.ini", … instead
                                    // of the same `/home/.../conf.d/` prefix repeated for every file.
                                    $isPathList = $value !== ''
                                        && str_contains($value, ',')
                                        && (str_starts_with($value, '/') || str_starts_with($value, 'C:'));
                                    ?>
                                    <div class="yii-debug-phpinfo-overview-card-row">
                                        <dt><?= Html::encode($key) ?></dt>
                                        <dd>
                                            <?php if ($boolHit && $variant !== null): ?>
                                                <span class="yii-debug-phpinfo-overview-pill" data-variant="<?= Html::encode($variant) ?>">
                                                    <?= Html::encode($value) ?>
                                                </span>
                                            <?php elseif ($isPathList): ?>
                                                <span class="yii-debug-phpinfo-overview-files">
                                                    <?php foreach (array_filter(array_map('trim', explode(',', $value))) as $file): ?>
                                                        <code class="yii-debug-phpinfo-overview-token"
                                                              title="<?= Html::encode($file) ?>">
                                                            <?= Html::encode(basename($file)) ?>
                                                        </code>
                                                    <?php endforeach; ?>
                                                </span>
                                            <?php else: ?>
                                                <?php
                                                $isPath = $value !== '' && (str_starts_with($value, '/') || str_starts_with($value, 'C:'));
                                                $display = $isPath ? $shortenPath($value) : $value;
                                                ?>
                                                <code title="<?= Html::encode($value) ?>"><?= Html::encode($display) ?></code>
                                            <?php endif; ?>
                                        </dd>
                                    </div>
                                <?php endforeach; ?>
                            </dl>
                        </details>
                    <?php endforeach; ?>
                </div>

                <?php if ($configureCommand !== ''): ?>
                    <details class="yii-debug-phpinfo-overview-details">
                        <summary>
                            <span class="yii-debug-phpinfo-overview-details-label">Configure Command</span>
                            <span class="yii-debug-phpinfo-overview-details-hint">click to expand</span>
                        </summary>
                        <pre class="yii-debug-phpinfo-overview-details-body"><?= Html::encode($configureCommand) ?></pre>
                    </details>
                <?php endif; ?>

            </section>

            <?= $modulesSrc ?>
        </div>
    </div>
</div>

