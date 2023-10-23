<?php

declare(strict_types=1);

namespace yii\debug\models\search;

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

use Yii;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;

use function array_keys;

/**
 * Search model for implementation of IdentityInterface
 *
 * @author Semen Dubina <yii2debug@sam002.net>
 * @since 2.0.10
 */
class User extends Model
{
    /**
     * @var Model|null implementation of IdentityInterface.
     */
    public Model|null $identityImplement = null;

    public function __get($name)
    {
        return $this->identityImplement->__get($name);
    }

    public function __set($name, $value)
    {
        $this->identityImplement->__set($name, $value);
    }

    public function init(): void
    {
        if (Yii::$app->user && Yii::$app->user->identityClass) {
            $identityImplementation = new Yii::$app->user->identityClass();

            if ($identityImplementation instanceof Model) {
                $this->identityImplement = $identityImplementation;
            }
        }
        parent::init();
    }

    public function rules(): array
    {
        return [[array_keys($this->identityImplement->getAttributes()), 'safe']];
    }

    public function attributes(): array
    {
        return $this->identityImplement->attributes();
    }

    /**
     * @throws InvalidConfigException
     */
    public function search($params): ActiveDataProvider|null
    {
        if ($this->identityImplement instanceof ActiveRecord) {
            return $this->searchActiveDataProvider($params);
        }

        return null;
    }

    /**
     * Search method for ActiveRecord
     *
     * @param array $params the data array to load model.
     *
     * @throws InvalidConfigException if model is not an instance of ActiveRecord.
     */
    private function searchActiveDataProvider(array $params): ActiveDataProvider
    {
        /** @var ActiveRecord $model */
        $model = $this->identityImplement;
        $query = $model::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        if (!($this->load($params) && $this->validate())) {
            return $dataProvider;
        }

        foreach ($model::getTableSchema()->columns as $attribute => $column) {
            if ($column->phpType === 'string') {
                $query->andFilterWhere(['like', $attribute, $model->getAttribute($attribute)]);
            } else {
                $query->andFilterWhere([$attribute => $model->getAttribute($attribute)]);
            }
        }

        return $dataProvider;
    }
}
