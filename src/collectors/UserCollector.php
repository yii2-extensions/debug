<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use DateTimeImmutable;
use DateTimeInterface;
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

use function array_filter;
use function array_keys;
use function get_mangled_object_vars;
use function is_array;
use function is_object;
use function is_string;
use function str_starts_with;

/**
 * Captures the authenticated identity for the User panel.
 */
class UserCollector extends Collector
{
    /**
     * Maximum nesting depth inspected while normalizing and redacting identity values.
     */
    private const int MAX_IDENTITY_DEPTH = 10;
    /**
     * Maximum entries inspected during one identity normalization pass.
     */
    private const int MAX_IDENTITY_NODES = 10000;

    /**
     * Component id of the user component, or a {@see User} instance to operate on directly.
     */
    public string|User $userComponent = 'user';

    /**
     * Capture policy applied to the persisted payload, created on first use.
     */
    private CapturePolicy|null $capturePolicy = null;

    /**
     * Returns the stable ID pairing this collector with the User panel.
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'user';
    }

    /**
     * Returns the value when it is already a string, otherwise renders it with {@see VarDumper::export()}.
     *
     * @param mixed $data Captured identity value.
     *
     * @return string Display string for the User panel.
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
     *
     * @return User|null Bound user component, or `null` when the configured id does not resolve to one.
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
     * Reads {@see Model::getAttributes()} when the identity is a {@see Model}; otherwise keeps only
     * public object properties.
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

        return self::normalizeStringKeyArray(self::publicProperties($identity));
    }

    /**
     * Snapshots the identity attributes, the RBAC roles, and the permissions for the active user.
     *
     * @return UserSnapshot|null Captured identity or Guest payload; `null` when the collector never started or the
     * user component cannot be resolved.
     */
    protected function snapshot(): UserSnapshot|null
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

        $rawIdentityData = $this->normalizeIdentityData($this->identityData($identity));

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

        return UserSnapshot::capture(
            [
                'id' => $identity->getId(),
                'identity' => $identityData,
                'attributes' => $attributes,
                'roles' => $roles,
                'permissions' => $permissions,
            ],
        );
    }

    /**
     * Returns the shared default policy used for persisted identity attributes.
     *
     * @return CapturePolicy Policy deciding which values are persisted and which are redacted.
     */
    private function capturePolicy(): CapturePolicy
    {
        return $this->capturePolicy ??= $this->module?->createCapturePolicy() ?? new CapturePolicy();
    }

    /**
     * Redacts identity values through the shared key policy while preserving native date precision and timezone.
     *
     * Only public properties of other objects are inspected. Depth and entry limits bound cycles and large trees;
     * sensitive branches are replaced without inspecting or formatting their values.
     *
     * @template TKey of array-key
     *
     * @param array<TKey, mixed> $values Identity attributes or nested public values.
     * @param int $depth Current array nesting depth.
     * @param int $nodes Entries inspected across the whole identity.
     *
     * @return array<TKey, mixed> Redacted, bounded values with dates normalized and live application objects untouched.
     */
    private function normalizeIdentityData(array $values, int $depth = 0, int &$nodes = 0): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if (++$nodes > self::MAX_IDENTITY_NODES) {
                $normalized[$key] = SensitiveDataRedactor::TRUNCATED;

                break;
            }

            if (is_string($key) && $this->capturePolicy()->isSensitiveKey($key)) {
                $normalized[$key] = SensitiveDataRedactor::PLACEHOLDER;

                continue;
            }

            if ($value instanceof DateTimeInterface) {
                $normalized[$key] = DateTimeImmutable::createFromInterface($value)->format('Y-m-d\TH:i:s.uP e');

                continue;
            }

            if (is_object($value)) {
                $value = self::publicProperties($value);
            }

            $normalized[$key] = is_array($value)
                ? ($depth >= self::MAX_IDENTITY_DEPTH
                    ? SensitiveDataRedactor::TRUNCATED
                    : $this->normalizeIdentityData($value, $depth + 1, $nodes))
                : $value;
        }

        return $normalized;
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

    /**
     * Reads public properties independently of the collector's access to related classes.
     *
     * @param object $value Object whose private and protected state must remain hidden.
     *
     * @return array<array-key, mixed> Public properties without invoking serialization or debug hooks.
     */
    private static function publicProperties(object $value): array
    {
        return array_filter(
            get_mangled_object_vars($value),
            static fn(string|int $key): bool => !str_starts_with((string) $key, "\0"),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
