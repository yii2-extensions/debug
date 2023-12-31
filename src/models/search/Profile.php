<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\models\search;

use yii\data\ArrayDataProvider;
use yii\debug\components\search\Filter;

/**
 * Search model for current request profiling log.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Mark Jebri <mark.github@yandex.ru>
 *
 * @since 2.0
 */
class Profile extends Base
{
    /**
     * @var string method attribute input search value.
     */
    public string $category = '';
    /**
     * @var int info attribute input search value.
     */
    public int $info = 0;

    public function rules(): array
    {
        return [
            [['category', 'info'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'category' => 'Category',
            'info' => 'Info',
        ];
    }

    /**
     * Returns data provider with filled models. Filter applied if needed.
     */
    public function search(array $params, array $models): ArrayDataProvider
    {
        $dataProvider = new ArrayDataProvider([
            'allModels' => $models,
            'pagination' => false,
            'sort' => [
                'attributes' => ['category', 'seq', 'duration', 'info'],
                'defaultOrder' => [
                    'duration' => SORT_DESC,
                ],
            ],
        ]);

        if (!($this->load($params) && $this->validate())) {
            return $dataProvider;
        }

        $filter = new Filter();

        $this->addCondition($filter, 'category', true);
        $this->addCondition($filter, 'info', true);

        $dataProvider->allModels = $filter->filter($models);

        return $dataProvider;
    }
}
