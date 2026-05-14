<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Helper\Encode;
use UIAwesome\Html\List\Li;
use UIAwesome\Html\Palpable\A;
use yii\debug\panels\UserPanel;
use yii\web\View;

/**
 * @var UserPanel $panel
 * @var View $this
 */

$encodedName = Encode::content($panel->getName());
?>

<h1 class="yii-debug-sr-only"><?= $encodedName ?></h1>

<?php
$panelData = is_array($panel->data) ? $panel->data : [];
$identity = $panelData['identity'] ?? null;
if ($identity !== null) {
    $items = [
        'nav' => [$encodedName],
        'content' => [
            $this->render('_identity', [
                'identity' => $identity,
                'attributes' => $panelData['attributes'] ?? null,
            ]),
        ],
    ];
    if (($panelData['rolesProvider'] ?? null) !== null || ($panelData['permissionsProvider'] ?? null) !== null) {
        $items['nav'][] = 'Roles and Permissions';
        $items['content'][] = $this->render('roles', ['panel' => $panel]);
    }

    if ($panel->canSwitchUser()) {
        $items['nav'][] = "Switch {$encodedName}";
        $items['content'][] = $this->render('switch', ['panel' => $panel]);
    }

    ?>
    <ul class="yii-debug-tabs">
        <?php
        foreach ($items['nav'] as $k => $item) {
            $link = A::tag()
                ->class($k === 0 ? 'yii-debug-tab-link is-active' : 'yii-debug-tab-link')
                ->href("#u-tab-{$k}")
                ->addAttribute('data-yii-debug-toggle', 'tab')
                ->addAttribute('role', 'tab')
                ->addAriaAttribute('controls', "u-tab-{$k}")
                ->addAriaAttribute('selected', $k === 0 ? 'true' : 'false')
                ->html($item)
                ->render();

            echo Li::tag()->class('yii-debug-tab')->html($link)->render();
        }
    ?>
    </ul>
    <div class="yii-debug-tab-content">
        <?php
    foreach ($items['content'] as $k => $item) {
        echo Div::tag()
            ->class($k === 0 ? 'yii-debug-tab-panel is-active' : 'yii-debug-tab-panel')
            ->id("u-tab-{$k}")
            ->html($item)
            ->render();
    }
    ?>
    </div>
    <?php
} else {
    echo 'Is guest.';
} ?>
