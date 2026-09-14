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

    /**
     * Reads an attribute from the identity model.
     *
     * @param string $name Attribute name.
     *
     * @return mixed Attribute value, or `null` when no identity model is available.
     */
    #[Override]
    public function __get($name): mixed
    {
        if ($this->identityImplement === null) {
            return null;
        }

        return $this->identityImplement->__get($name);
    }

    /**
     * Writes an attribute to the identity model, ignoring the call when no identity model is available.
     *
     * @param string $name Attribute name.
     * @param mixed $value Value to assign.
     */
    #[Override]
    public function __set($name, $value): void
    {
        if ($this->identityImplement === null) {
            return;
        }

        $this->identityImplement->__set($name, $value);
    }

    /**
     * @return array<int|string, string> Attribute names forwarded from the identity model; empty when unavailable.
     */
    #[Override]
    public function attributes(): array
    {
        if ($this->identityImplement === null) {
            return [];
        }

        return $this->identityImplement->attributes();
    }

    /**
     * @return string Query-string prefix that scopes this form's filter parameters.
     */
    #[Override]
    public function formName(): string
    {
        return FilterPrefix::USER;
    }

    /**
     * Instantiates the configured `identityClass` so the search form mirrors the identity model's attributes.
     */
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

    /**
     * @return array<int, array<int|string, mixed>> Safe-attribute rules mirroring the identity model; empty when no
     * identity model is available.
     */
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
     * @throws InvalidConfigException when the table schema cannot be resolved during AR filtering.
     *
     * @return DataProviderInterface Provider over the identity model, empty for non-AR identities.
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
     * @param ActiveRecord $model Identity model whose table schema drives the per-column filters.
     *
     * @throws InvalidConfigException when the table schema cannot be resolved.
     *
     * @return ActiveDataProvider Provider whose query carries the per-column filters.
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
