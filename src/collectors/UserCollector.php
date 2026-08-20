<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Helper\SensitiveDataRedactor;
use PHPForge\Debug\Panel\User\UserSnapshot;
use Throwable;
use Yii;
use yii\base\Model;
use yii\di\Instance;
use yii\helpers\VarDumper;
use yii\rbac\{BaseManager, Item};
use yii\web\{IdentityInterface, User};

use function array_keys;
use function get_object_vars;
use function is_string;

/**
 * Captures the authenticated identity for the User panel.
 *
 * Snapshots the identity's attributes, RBAC roles, and permissions. A configured user component without an active
 * identity captures the shared Guest payload, while a missing user component keeps the panel absent.
 *
 * Usage example:
 *
 * ```php
 * $snapshot = (new \yii\debug\collectors\UserCollector())->capture();
 * ```
 */
class UserCollector extends Collector
{
    /**
     * Component id of the user component, or a {@see User} instance to operate on directly.
     */
    public string|User $userComponent = 'user';

    private CapturePolicy|null $capturePolicy = null;

    /**
     * Snapshots the identity attributes, the RBAC roles, and the permissions for the active user.
     *
     * @return UserSnapshot|null Captured identity or Guest payload; `null` when the collector never started or the
     * user component cannot be resolved.
     */
    public function capture(): UserSnapshot|null
    {
        if (!$this->isStarted()) {
            return null;
        }

        $user = $this->getUser();

        if ($user === null) {
            return null;
        }

        if (!$user->identity instanceof IdentityInterface) {
            return UserSnapshot::capture(
                [
                    'id' => null,
                    'identity' => null,
                    'attributes' => null,
                    'roles' => null,
                    'permissions' => null,
                ],
            );
        }

        $identity = $user->identity;

        $userId = $user->getId();

        $roles = null;
        $permissions = null;

        $module = $this->module;

        if ($module !== null && $userId !== null) {
            try {
                $authManager = Instance::ensure($module->authManager, BaseManager::class);

                $roles = $this->normalizeRbacItems($authManager->getRolesByUser($userId));
                $permissions = $this->normalizeRbacItems($authManager->getPermissionsByUser($userId));
            } catch (Throwable) {
                // Ignore auth manager misconfiguration so the identity panel remains available.
            }
        }

        $rawIdentityData = $this->identityData($identity);

        $identityData = [];

        foreach ($rawIdentityData as $key => $value) {
            $identityData[$key] = $this->capturePolicy()->isSensitiveKey($key)
                ? SensitiveDataRedactor::PLACEHOLDER
                : VarDumper::dumpAsString($value);
        }

        // If the identity is a model, let it specify the attribute labels
        if ($identity instanceof Model) {
            $attributes = [];

            foreach (array_keys($identityData) as $attribute) {
                $attributes[] = [
                    'attribute' => $attribute,
                    'label' => $identity->getAttributeLabel($attribute),
                ];
            }
        } else {
            // Let the DetailView widget figure the labels out
            $attributes = null;
        }

        return UserSnapshot::capture([
            'id' => $identity->getId(),
            'identity' => $identityData,
            'attributes' => $attributes,
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Returns the stable ID pairing this collector with the User panel.
     *
     * Usage example:
     *
     * ```php
     * $id = (new \yii\debug\collectors\UserCollector())->id();
     * ```
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'user';
    }

    /**
     * Returns the value when it is already a string, otherwise renders it with {@see VarDumper::export()}.
     */
    protected function dataToString(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        return VarDumper::export($data);
    }

    /**
     * Returns the user component bound to this collector, or `null` when the configured component id does not resolve
     * to a {@see User} instance.
     */
    protected function getUser(): User|null
    {
        if ($this->userComponent instanceof User) {
            return $this->userComponent;
        }

        $user = Yii::$app->get($this->userComponent, false);

        return $user instanceof User ? $user : null;
    }

    /**
     * Returns the identity attributes as a string-keyed map suitable for {@see \yii\widgets\DetailView::$model}.
     *
     * Reads {@see Model::getAttributes()} when the identity is a {@see Model}; otherwise falls back to
     * {@see get_object_vars()} on the identity object.
     *
     * @param IdentityInterface $identity Active identity object.
     *
     * @return array<string, mixed> Attribute map ready to feed the detail view.
     */
    protected function identityData(IdentityInterface $identity): array
    {
        if ($identity instanceof Model) {
            return self::normalizeStringKeyArray($identity->getAttributes());
        }

        return self::normalizeStringKeyArray(get_object_vars($identity));
    }

    /**
     * Returns the shared default policy used for persisted identity attributes.
     */
    private function capturePolicy(): CapturePolicy
    {
        return $this->capturePolicy ??= new CapturePolicy();
    }

    /**
     * Narrows the RBAC items returned by the auth manager into typed rows suitable for the User panel providers.
     *
     * @param array<int|string, Item> $items RBAC items indexed by item name.
     *
     * @return array<int, array{
     *   name: string,
     *   description: string,
     *   ruleName: string|null,
     *   data: string,
     *   createdAt: int,
     *   updatedAt: int
     * }> Rows in iteration order.
     */
    private function normalizeRbacItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $normalized[] = [
                'name' => $item->name,
                'description' => $item->description,
                'ruleName' => $item->ruleName,
                'data' => $this->dataToString($item->data),
                'createdAt' => $item->createdAt,
                'updatedAt' => $item->updatedAt,
            ];
        }

        return $normalized;
    }

    /**
     * Stringifies every key of the input array, so the detail view sees a `string => mixed` map.
     *
     * @param array<int|string, mixed> $data Raw identity data.
     *
     * @return array<string, mixed> Same entries with their keys coerced to strings.
     */
    private static function normalizeStringKeyArray(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}
