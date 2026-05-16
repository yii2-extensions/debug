<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\base\Model;

/**
 * Stub model that does not implement any search filter interface to test the behavior of the debug module when a model
 * does not have search capabilities.
 */
final class NoSearchFilterModel extends Model {}
