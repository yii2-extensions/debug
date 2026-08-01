<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\{Div, P, Pre};
use UIAwesome\Html\Heading\{H1, H3};
use UIAwesome\Html\Phrasing\{Code, Span, Strong};
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Table\{Table, Tbody, Td, Th, Thead, Tr};
use yii\debug\helpers\{CellMore, EmptyState};
use yii\debug\panels\InertiaPanel;

/** @var InertiaPanel $panel Panel providing the detail content. */
$data = is_array($panel->data) ? $panel->data : [];

$page = is_array($data['page'] ?? null) ? $data['page'] : null;
$requestHeaders = is_array($data['requestHeaders'] ?? null) ? $data['requestHeaders'] : [];
$sharedKeys = is_array($data['sharedKeys'] ?? null) ? $data['sharedKeys'] : [];
$statusCode = is_int($data['statusCode'] ?? null) ? $data['statusCode'] : 0;
$location = is_string($data['location'] ?? null) ? $data['location'] : null;

$component = is_string($page['component'] ?? null) ? $page['component'] : '';
$url = is_string($page['url'] ?? null) ? $page['url'] : '';
$version = is_scalar($page['version'] ?? null) ? (string) $page['version'] : '';
$props = is_array($page['props'] ?? null) ? $page['props'] : [];

$isXhr = isset($requestHeaders['X-Inertia']);
$isPartial = $isXhr
    && (isset($requestHeaders['X-Inertia-Partial-Data']) || isset($requestHeaders['X-Inertia-Partial-Except']));

$visit = match (true) {
    $statusCode === 409 => 'Version conflict',
    $isPartial => 'Partial reload',
    $isXhr => 'Inertia visit',
    default => 'Full page load',
};

$typeOf = static fn(mixed $value): string => match (true) {
    is_array($value) => 'array(' . count($value) . ')',
    is_string($value) => 'string(' . strlen($value) . ')',
    is_int($value) => 'int',
    is_float($value) => 'float',
    is_bool($value) => 'bool',
    $value === null => 'null',
    default => gettype($value),
};

$previewOf = static function (mixed $value): string {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $json = is_string($json) ? $json : '—';

    return mb_strlen($json) > 100 ? mb_substr($json, 0, 100) . '…' : $json;
};

$summaryItems = [
    Span::tag()
        ->html(Strong::tag()->content($component !== '' ? $component : '—')),
    Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·'),
    Span::tag()->content($visit),
];

if ($page !== null) {
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·');
    $summaryItems[] = Span::tag()
        ->html(
            Strong::tag()->content((string) count($props)),
            count($props) === 1 ? ' prop' : ' props',
        );
}
?>
<?= H1::tag()
    ->class('yii-debug-sr-only')
    ->content('Inertia') ?>
<?= Header::tag()
    ->class('yii-debug-grid-summary')
    ->html(...$summaryItems) ?>
<?php if ($page === null): ?>
    <?php if ($statusCode === 409): ?>
        <?= EmptyState::card(
            'Version conflict interrupted this visit',
            P::tag()->html(
                'The client asset version sent in ',
                Code::tag()->content('X-Inertia-Version'),
                ' no longer matches the server version, so Inertia answered ',
                Code::tag()->content('409'),
                ' and asked the client to reload the full page.',
            ),
            P::tag()->html(
                'Reload target: ',
                Code::tag()->content($location ?? '—'),
            ),
        ) ?>
    <?php else: ?>
        <?= EmptyState::card(
            'No Inertia page in this request',
            P::tag()->html(
                'This response was not produced by ',
                Code::tag()->content('Inertia::render()'),
                ', so there is no page object to inspect.',
            ),
            P::tag()->content(
                'Both full page loads and Inertia XHR visits populate this view; plain JSON endpoints, '
                    . 'redirects, and asset requests do not.',
            ),
        ) ?>
    <?php endif; ?>
    <?php return; ?>
<?php endif; ?>
<?php
$infoRows = [
    Tr::tag()->html(
        Th::tag()->content('Component'),
        Td::tag()->content($component !== '' ? $component : '—'),
    ),
    Tr::tag()->html(
        Th::tag()->content('URL'),
        Td::tag()->content($url !== '' ? $url : '—'),
    ),
    Tr::tag()->html(
        Th::tag()->content('Version'),
        Td::tag()->content($version !== '' ? $version : '—'),
    ),
    Tr::tag()->html(
        Th::tag()->content('Visit'),
        Td::tag()->content($visit),
    ),
    Tr::tag()->html(
        Th::tag()->content('Status'),
        Td::tag()->content((string) $statusCode),
    ),
];

foreach ($requestHeaders as $name => $value) {
    if ($name === 'X-Inertia') {
        continue;
    }

    $infoRows[] = Tr::tag()->html(
        Th::tag()->content($name),
        Td::tag()->content($value),
    );
}

$propRows = [];
$i = 1;

foreach ($props as $key => $value) {
    $origin = in_array((string) $key, $sharedKeys, true)
        ? Span::tag()->class('yii-debug-badge yii-debug-badge-info')->content('shared')
        : Span::tag()->class('yii-debug-badge yii-debug-badge-muted')->content('page');

    $propRows[] = Tr::tag()
        ->html(
            Td::tag()->content((string) $i),
            Td::tag()
                ->class('yii-debug-cell-mono')
                ->html(Strong::tag()->content((string) $key)),
            Td::tag()
                ->class('yii-debug-cell-pill')
                ->html($origin),
            Td::tag()
                ->class('yii-debug-cell-mono')
                ->content($typeOf($value)),
            Td::tag()
                ->class('yii-debug-cell-mono')
                ->content($previewOf($value)),
        );

    $i++;
}

$pageJson = json_encode($page, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$pageJson = is_string($pageJson) ? $pageJson : '{}';

$rawBlock = Pre::tag()
    ->content($pageJson)
    ->render();

if (strlen($pageJson) > 600) {
    $rawBlock = CellMore::wrap($rawBlock);
}
?>
<?= Div::tag()
    ->class('yii-debug-table-wrap')
    ->html(
        Table::tag()
            ->class('yii-debug-table yii-debug-table-mono')
            ->html(Tbody::tag()->html(...$infoRows)),
    ) ?>
<?= H3::tag()->content('Props') ?>
<?php if ($propRows === []): ?>
    <?= P::tag()->content('The page rendered without props.') ?>
<?php else: ?>
    <?= Div::tag()
        ->class('yii-debug-table-wrap')
        ->html(
            Table::tag()
                ->class('yii-debug-table')
                ->html(
                    Thead::tag()->html(
                        Tr::tag()->html(
                            Th::tag()->content('#'),
                            Th::tag()->content('Prop'),
                            Th::tag()->content('Origin'),
                            Th::tag()->content('Type'),
                            Th::tag()->content('Value'),
                        ),
                    ),
                    Tbody::tag()->html(...$propRows),
                ),
        ) ?>
<?php endif; ?>
<?= H3::tag()->content('Raw payload') ?>
<?= $rawBlock;
