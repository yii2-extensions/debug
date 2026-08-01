<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use Override;
use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;

/**
 * Backs the filter form above the Mail panel grid of messages dispatched during the request.
 */
class MailSearch extends Base
{
    /**
     * Submitted value for the `bcc` filter (substring match).
     */
    public string $bcc = '';
    /**
     * Submitted value for the `body` filter (substring match).
     */
    public string $body = '';
    /**
     * Submitted value for the `cc` filter (substring match).
     */
    public string $cc = '';
    /**
     * Submitted value for the `charset` filter (substring match).
     */
    public string $charset = '';
    /**
     * Submitted value for the captured file name filter (substring match).
     */
    public string $file = '';
    /**
     * Submitted value for the `from` filter (substring match).
     */
    public string $from = '';
    /**
     * Submitted value for the `headers` filter (substring match).
     */
    public string $headers = '';
    /**
     * Submitted value for the `reply` filter (substring match).
     */
    public string $reply = '';
    /**
     * Submitted value for the `subject` filter (substring match).
     */
    public string $subject = '';
    /**
     * Submitted value for the `to` filter (substring match).
     */
    public string $to = '';

    #[Override]
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

    #[Override]
    public function rules(): array
    {
        return [
            [['from', 'to', 'reply', 'cc', 'bcc', 'subject', 'body', 'charset'], 'safe'],
        ];
    }

    /**
     * Returns an {@see ArrayDataProvider} over the captured mail messages, applying the loaded filter values.
     *
     * @param array<int|string, mixed> $params Raw request parameters consumed by {@see Model::load()}.
     * @param array<int, array<string, mixed>> $models Captured mail records to wrap and filter.
     */
    public function search(array $params, array $models): ArrayDataProvider
    {
        $dataProvider = new ArrayDataProvider(
            [
                'allModels' => $models,
                'pagination' => GridViewConfig::paginationFromRequest(20),
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

        $this->addCondition('from', true);
        $this->addCondition('to', true);
        $this->addCondition('reply', true);
        $this->addCondition('cc', true);
        $this->addCondition('bcc', true);
        $this->addCondition('subject', true);
        $this->addCondition('body', true);
        $this->addCondition('charset', true);

        $dataProvider->allModels = $this->filter($models);

        return $dataProvider;
    }
}
