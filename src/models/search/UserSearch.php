<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use Override;
use PHPForge\Debug\Data\FilterPrefix;
use Yii;
use yii\base\{InvalidConfigException, Model};
use yii\data\{ActiveDataProvider, ArrayDataProvider, DataProviderInterface};
use yii\db\ActiveRecord;

use function array_keys;

/**
 * Backs the User Switch panel's search form, delegating attribute access to the application's identity model.
 *
 * Instantiates the configured `identityClass` and forwards `__get`/`__set`/`attributes()` to it, so the panel can
 * surface a search form whose fields automatically match whatever identity model the host application uses.
 */
class UserSearch extends Model implements UserSearchInterface
{
    /**
     * Identity model instance resolved from the configured user component, or `null` when no user component exists.
     */
    public Model|null $identityImplement = null;

    #[Override]
    public function __get($name): mixed
    {
        if ($this->identityImplement === null) {
            return null;
        }

        return $this->identityImplement->__get($name);
    }

    #[Override]
    public function __set($name, $value): void
    {
        if ($this->identityImplement === null) {
            return;
        }

        $this->identityImplement->__set($name, $value);
    }

    #[Override]
    public function attributes(): array
    {
        if ($this->identityImplement === null) {
            return [];
        }

        return $this->identityImplement->attributes();
    }

    #[Override]
    public function formName(): string
    {
        return FilterPrefix::USER;
    }

    public function init(): void
    {
        $user = Yii::$app->user ?? null;

        if ($user !== null) {
            $identityImplementation = new ($user->identityClass)();

            if ($identityImplementation instanceof Model) {
                $this->identityImplement = $identityImplementation;
            }
        }
    }

    #[Override]
    public function rules(): array
    {
        if ($this->identityImplement === null) {
            return [];
        }

        return [[array_keys($this->identityImplement->getAttributes()), 'safe']];
    }

    /**
     * Returns a data provider over the identity model.
     *
     * Yields an {@see ActiveDataProvider} when the identity is an {@see ActiveRecord}; falls back to an empty
     * {@see ArrayDataProvider} otherwise (so non-AR identities surface an empty user-switch grid instead of failing).
     *
     * @param array<int|string, mixed> $params Raw request parameters consumed by {@see Model::load()}.
     *
     * @throws InvalidConfigException When the table schema cannot be resolved during AR filtering.
     */
    public function search(array $params): DataProviderInterface
    {
        if ($this->identityImplement instanceof ActiveRecord) {
            return $this->searchActiveDataProvider($params, $this->identityImplement);
        }

        return new ArrayDataProvider();
    }

    /**
     * Builds the data provider for an {@see ActiveRecord} identity model, applying per-column filters.
     *
     * String columns are matched with `LIKE`; all other columns use exact matching.
     *
     * @param array<int|string, mixed> $params Raw request parameters consumed by {@see Model::load()}.
     *
     * @throws InvalidConfigException When the table schema cannot be resolved.
     */
    private function searchActiveDataProvider(array $params, ActiveRecord $model): ActiveDataProvider
    {
        $query = $model::find();

        $dataProvider = new ActiveDataProvider(['query' => $query]);

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
