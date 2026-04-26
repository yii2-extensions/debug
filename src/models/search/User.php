<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\models\search;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;

/**
 * Search model for implementation of IdentityInterface
 *
 * @author Semen Dubina <yii2debug@sam002.net>
 * @since 2.0.10
 */
class User extends Model
{
    /**
     * @var Model|null implementation of IdentityInterface
     */
    public $identityImplement = null;

    public function __get($name)
    {
        if ($this->identityImplement === null) {
            return null;
        }
        return $this->identityImplement->__get($name);
    }

    public function __set($name, $value)
    {
        if ($this->identityImplement === null) {
            return;
        }
        $this->identityImplement->__set($name, $value);
    }

    public function attributes()
    {
        if ($this->identityImplement === null) {
            return [];
        }
        return $this->identityImplement->attributes();
    }

    public function init(): void
    {
        $user = \Yii::$app->user ?? null;
        if ($user !== null) {
            $identityClass = $user->identityClass;
            if ($identityClass !== null) {
                $identityImplementation = new $identityClass();
                if ($identityImplementation instanceof Model) {
                    $this->identityImplement = $identityImplementation;
                }
            }
        }
        parent::init();
    }

    public function rules()
    {
        if ($this->identityImplement === null) {
            return [];
        }
        return [[array_keys($this->identityImplement->getAttributes()), 'safe']];
    }

    /**
     * @param array<int|string, mixed> $params
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function search(array $params): ActiveDataProvider|null
    {
        if ($this->identityImplement instanceof ActiveRecord) {
            return $this->searchActiveDataProvider($params, $this->identityImplement);
        }

        return null;
    }

    /**
     * Search method for ActiveRecord.
     *
     * @param array<int|string, mixed> $params the data array to load model.
     *
     * @throws \yii\base\InvalidConfigException
     */
    private function searchActiveDataProvider(array $params, ActiveRecord $model): ActiveDataProvider
    {
        $query = $model::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        if (!($this->load($params) && $this->validate())) {
            return $dataProvider;
        }

        foreach ($model::getTableSchema()->columns as $attribute => $column) {
            $name = (string) $attribute;
            if ($column->phpType === 'string') {
                $query->andFilterWhere(['like', $name, $model->getAttribute($name)]);
            } else {
                $query->andFilterWhere([$name => $model->getAttribute($name)]);
            }
        }

        return $dataProvider;
    }
}
