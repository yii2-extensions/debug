<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\models\search;

use yii\data\ArrayDataProvider;
use yii\debug\components\search\Filter;

/**
 * Mail represents the model behind the search form about current send emails.
 */
class Mail extends Base
{
    /**
     * BCC attribute input search value.
     */
    public string $bcc = '';
    /**
     * Body attribute input search value.
     */
    public string $body = '';
    /**
     * CC attribute input search value.
     */
    public string $cc = '';
    /**
     * Charset attribute input search value.
     */
    public string $charset = '';
    /**
     * File attribute input search value.
     */
    public string $file = '';
    /**
     * From attribute input search value.
     */
    public string $from = '';
    /**
     * Headers attribute input search value.
     */
    public string $headers = '';
    /**
     * Reply attribute input search value.
     */
    public string $reply = '';
    /**
     * Subject attribute input search value.
     */
    public string $subject = '';
    /**
     * To attribute input search value.
     */
    public string $to = '';

    public function attributeLabels()
    {
        return [
            'from' => 'From',
            'to' => 'To',
            'reply' => 'Reply',
            'cc' => 'Copy receiver',
            'bcc' => 'Hidden copy receiver',
            'subject' => 'Subject',
            'charset' => 'Charset',
        ];
    }

    public function rules()
    {
        return [
            [['from', 'to', 'reply', 'cc', 'bcc', 'subject', 'body', 'charset'], 'safe'],
        ];
    }

    /**
     * Returns data provider with filled models. Filter applied if needed.
     *
     * @param array<int|string, mixed> $params
     * @param array<int, array<string, mixed>> $models
     */
    public function search(array $params, array $models): ArrayDataProvider
    {
        $dataProvider = new ArrayDataProvider(
            [
                'allModels' => $models,
                'pagination' => ['pageSize' => 20],
                'sort' => [
                    'attributes' => [
                        'from',
                        'to',
                        'reply',
                        'cc',
                        'bcc',
                        'subject',
                        'body',
                        'charset',
                    ],
                ],
            ],
        );

        if (!($this->load($params) && $this->validate())) {
            return $dataProvider;
        }

        $filter = new Filter();

        $this->addCondition($filter, 'from', true);
        $this->addCondition($filter, 'to', true);
        $this->addCondition($filter, 'reply', true);
        $this->addCondition($filter, 'cc', true);
        $this->addCondition($filter, 'bcc', true);
        $this->addCondition($filter, 'subject', true);
        $this->addCondition($filter, 'body', true);
        $this->addCondition($filter, 'charset', true);

        $dataProvider->allModels = $filter->filter($models);

        return $dataProvider;
    }
}
