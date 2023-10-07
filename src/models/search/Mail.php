<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use yii\data\ArrayDataProvider;
use yii\debug\components\search\Filter;

/**
 * Mail represents the model behind the search form about current send emails.
 */
class Mail extends Base
{
    /**
     * @var string from attribute input search value.
     */
    public string $from = '';
    /**
     * @var string to attribute input search value.
     */
    public string $to = '';
    /**
     * @var string reply attribute input search value.
     */
    public string $reply = '';
    /**
     * @var string cc attribute input search value.
     */
    public string $cc = '';
    /**
     * @var string bcc attribute input search value.
     */
    public string $bcc = '';
    /**
     * @var string subject attribute input search value.
     */
    public string $subject = '';
    /**
     * @var string body attribute input search value.
     */
    public string $body = '';
    /**
     * @var string charset attribute input search value.
     */
    public string $charset = '';
    /**
     * @var string headers attribute input search value.
     */
    public string $headers = '';
    /**
     * @var string file attribute input search value.
     */
    public string $file;

    
    public function rules(): array
    {
        return [
            [['from', 'to', 'reply', 'cc', 'bcc', 'subject', 'body', 'charset'], 'safe'],
        ];
    }

    
    public function attributeLabels(): array
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

    /**
     * Returns data provider with filled models. Filter applied if needed.
     */
    public function search(array $params, array $models): ArrayDataProvider
    {
        $dataProvider = new ArrayDataProvider([
            'allModels' => $models,
            'pagination' => [
                'pageSize' => 20,
            ],
            'sort' => [
                'attributes' => ['from', 'to', 'reply', 'cc', 'bcc', 'subject', 'body', 'charset'],
            ],
        ]);

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
