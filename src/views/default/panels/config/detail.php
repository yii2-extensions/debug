<?php

declare (strict_types=1);

use yii\debug\panels\ConfigPanel;
use yii\web\View;

/**
 * @var ConfigPanel $panel
 * @var View $this
 */
$extensions = $panel->getExtensions();
?>
<h1>Configuration</h1>

<?php
$formatLanguage = static function ($locale) {
    if (class_exists('Locale', false)) {
        $region = Locale::getDisplayLanguage($locale, 'en');
        $language = Locale::getDisplayRegion($locale, 'en');
        return ' (' . implode(',', array_filter([$language, $region])) . ')';
    }
    return '';
};
$app = $panel->data['application'];
echo $this->render('table', [
    'caption' => 'Application Configuration',
    'values' => [
        'Yii Version' => $app['yii'],
        'Application Name' => $app['name'],
        'Application Version' => $app['version'],
        'Current Language' => !empty($app['language']) ? $app['language'] . $formatLanguage($app['language']) : '',
        'Source Language' => !empty($app['sourceLanguage']) ? $app['sourceLanguage'] . $formatLanguage($app['sourceLanguage']) : '',
        'Charset' => !empty($app['charset']) ? $app['charset'] : '',
        'Environment' => $app['env'],
        'Debug Mode' => $app['debug'] ? 'Yes' : 'No',
    ],
]);

if (!empty($extensions)) {
    echo $this->render('table', [
        'caption' => 'Installed Extensions',
        'values' => $extensions,
    ]);
}

$memcache = 'Disabled';
if ($panel->data['php']['memcache']) {
    $memcache = 'Enabled (memcache)';
} elseif ($panel->data['php']['memcached']) {
    $memcache = 'Enabled (memcached)';
}

echo $this->render('table', [
    'caption' => 'PHP Configuration',
    'values' => [
        'PHP Version' => $panel->data['php']['version'],
        'Xdebug' => $panel->data['php']['xdebug'] ? 'Enabled' : 'Disabled',
        'APC' => $panel->data['php']['apc'] ? 'Enabled' : 'Disabled',
        'Memcache' => $memcache,
    ],
]);

echo $panel->getPhpInfo();
