<p align="center">
    <a href="https://github.com/yii2-extensions/debug" target="_blank">
        <img src="https://www.yiiframework.com/image/yii_logo_light.svg" height="100px;">
    </a>
    <h1 align="center">Debug</h1>
    <br>
</p>

<p align="center">
    <a href="https://www.php.net/releases/8.1/en.php" target="_blank">
        <img src="https://img.shields.io/badge/PHP-%3E%3D8.1-787CB5" alt="php-version">
    </a>
    <a href="https://github.com/yiisoft/yii2/tree/2.2" target="_blank">
        <img src="https://img.shields.io/badge/Yii2%20version-2.2-blue" alt="yii2-version">
    </a>
    <a href="https://github.com/yii2-extensions/debug/actions/workflows/build.yml" target="_blank">
        <img src="https://github.com/yii2-extensions/debug/actions/workflows/build.yml/badge.svg" alt="PHPUnit">
    </a>
    <a href="https://codecov.io/gh/yii2-extensions/debug" target="_blank">
        <img src="https://codecov.io/gh/yii2-extensions/debug/branch/main/graph/badge.svg?token=MF0XUGVLYC" alt="Codecov">
    </a>
    <a href="https://github.com/yii2-extensions/debug/actions/workflows/static.yml" target="_blank">
        <img src="https://github.com/yii2-extensions/debug/actions/workflows/static.yml/badge.svg" alt="PHPStan">
    </a>
    <a href="https://github.com/yii2-extensions/debug/actions/workflows/static.yml" target="_blank">
        <img src="https://img.shields.io/badge/PHPStan%20level-1-blue" alt="PHPStan level">
    </a>    
    <a href="https://github.styleci.io/repos/699842423?branch=main" target="_blank">
        <img src="https://github.styleci.io/repos/699842423/shield?branch=main" alt="Code style">
    </a>        
</p>

## Installation

The preferred way to install this extension is through [composer](https://getcomposer.org/download/).

Either run

```
php composer.phar require --dev --prefer-dist yii2-extensions/debug
```

or add

```
"yii2-extensions/debug": "dev-main"
```

to the require-dev section of your `composer.json` file.

## Usage

Once the extension is installed, simply modify your application configuration as follows:

```php
return [
    'bootstrap' => ['debug'],
    'modules' => [
        'debug' => [
            'class' => 'yii\debug\Module',
            // uncomment and adjust the following to add your IP if you are not connecting from localhost.
            //'allowedIPs' => ['127.0.0.1', '::1'],
        ],
        // ...
    ],
    ...
];
```

You will see a debugger toolbar showing at the bottom of every page of your application.
You can click on the toolbar to see more detailed debug information.


### Open Files in IDE

You can create a link to open files in your favorite IDE with this configuration:

```php
return [
    'bootstrap' => ['debug'],
    'modules' => [
        'debug' => [
            'class' => 'yii\debug\Module',
            'traceLine' => '<a href="phpstorm://open?url={file}&line={line}">{file}:{line}</a>',
            // uncomment and adjust the following to add your IP if you are not connecting from localhost.
            //'allowedIPs' => ['127.0.0.1', '::1'],
        ],
        // ...
    ],
    ...
];
```

You must make some changes to your OS. See these examples: 
 - PHPStorm: https://github.com/aik099/PhpStormProtocol
 - Sublime Text 3 on Windows or Linux: https://packagecontrol.io/packages/subl%20protocol
 - Sublime Text 3 on Mac: https://github.com/inopinatus/sublime_url

### Virtualized or dockerized

If your application is run under a virtualized or dockerized environment, it is often the case that the application's 
base path is different inside of the virtual machine or container than on your host machine. For the links work in those
 situations, you can configure `tracePathMappings` like this (change the path to your app):

```php
'tracePathMappings' => [
    '/app' => '/path/to/your/app',
],
```

Or you can create a callback for `traceLine` for even more control:

```php
'traceLine' => function($options, $panel) {
    $filePath = $options['file'];
    if (StringHelper::startsWith($filePath, Yii::$app->basePath)) {
        $filePath = '/path/to/your/app' . substr($filePath, strlen(Yii::$app->basePath));
    }
    return strtr('<a href="ide://open?url=file://{file}&line={line}">{text}</a>', ['{file}' => $filePath]);
},
```

### Configure with yiisoft/config

> Add the following code to your `config/config-plugin` file in your application.

```php
'config-plugin' => [
    'web' => [
        '$yii2-debug', // add this line
        'web/*.php'
    ],
],
```

> For activate the debug toolbar, add in your config/params.php file in your application.

```php
return [
    'yii2.debug' => true,
];
```	

> For change allowed IPs, add in your config/params.php file in your application.

```php
return [
    'yii2.debug.allowedIPs' => ['192.168.1.1'],
];
```

> For class map module, add in your config/params.php file in your application.

```php
use App\YourClass;

return [
    'yii2.debug.classMap' => [
        'class' => YourClass::class,
    ],
];
```


## Testing

[Check the documentation testing](/docs/testing.md) to learn about testing.

## Our social networks

[![Twitter](https://img.shields.io/badge/twitter-follow-1DA1F2?logo=twitter&logoColor=1DA1F2&labelColor=555555?style=flat)](https://twitter.com/Terabytesoftw)

## License

The MIT License. Please see [License File](LICENSE.md) for more information.
