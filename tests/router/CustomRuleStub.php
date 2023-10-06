<?php

declare(strict_types=1);

namespace yiiunit\debug\router;

use yii\web\UrlRuleInterface;

class CustomRuleStub implements UrlRuleInterface
{
    public function parseRequest($manager, $request)
    {
        return false;
    }

    public function createUrl($manager, $route, $params)
    {
        return false;
    }
}
