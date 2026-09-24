<?php

declare(strict_types=1);

namespace yii\debug\service;

use InvalidArgumentException;
use PHPForge\Debug\Panel as PortablePanel;
use PHPForge\Debug\Registration\{EntryParser, PanelOverride, PanelRegistration, PanelRegistry};
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\{ComponentResolver, Module, Panel};
use yii\debug\exception\Message;
use yii\debug\panels\{JsonPanel, ProviderPanel};

use function array_diff_key;
use function array_flip;
use function array_intersect_key;
use function is_array;
use function is_string;
use function is_subclass_of;

/**
 * Instantiates the configured debug panels and resolves the catalog describing their display order.
 *
 * Built-in panels are omitted when their optional package is unavailable. Explicit application configuration remains
 * authoritative and may still register a custom panel under the same ID.
 *
 * Panels are published on {@see Module::$panels} as they are built, so a panel reading a sibling while it binds
 * itself sees every entry registered before it.
 */
class PanelRegistrar
{
    /**
     * @param Module $module Debug module owning the panels and read for configuration at call time.
     */
    public function __construct(protected readonly Module $module) {}

    /**
     * Instantiates every configured panel, binds it to the module, and returns the catalog in display order.
     *
     * An entry declaring `enabled` as `false` is skipped before its class is resolved and is reported by
     * {@see PanelRegistry::disabled()}; `title` and `icon` overrides apply to portable panels only, because a Yii
     * panel renders the metadata it declares itself. A panel whose {@see Panel::isEnabled()} returns `false` is
     * dropped after construction.
     *
     * @param array<string, array<string, mixed>|class-string<Panel>|class-string<PortablePanel>|string> $core
     * Built-in panel definitions indexed by panel ID.
     * @param array<array-key, array<string, mixed>|Panel|PortablePanel|string> $configured Panel definitions declared
     * by the application.
     *
     * @throws InvalidConfigException when a definition, a registration option, or the resolved catalog is invalid.
     *
     * @return PanelCatalog Registered panels in display order and the catalog describing them.
     */
    public function register(array $core, array $configured): PanelCatalog
    {
        $this->module->panels = [];

        $overrides = [];

        foreach (CoreDefinitions::merge($core, $configured) as $key => $definition) {
            $override = null;

            if (is_array($definition)) {
                $override = self::panelOverride($definition);

                if ($override->enabled === false) {
                    if (is_string($key)) {
                        $overrides[$key] = $override;
                    }

                    continue;
                }

                $definition = array_diff_key($definition, array_flip(PanelOverride::KEYS));
            }

            $panel = $this->buildPanel($key, $definition);

            if ($override !== null && !$panel instanceof ProviderPanel) {
                self::assertNoMetadataOverride($panel->id, $override);
            }

            if (isset($this->module->panels[$panel->id])) {
                throw new InvalidConfigException(
                    Message::PANEL_ID_DUPLICATE->getMessage($panel->id),
                );
            }

            if ($panel->isEnabled() === false) {
                continue;
            }

            $this->module->panels[$panel->id] = $panel;

            if ($override !== null) {
                $overrides[$panel->id] = $override;
            }
        }

        return $this->resolveCatalog($overrides, $core);
    }

    /**
     * Wraps a provider-owned declarative panel in the host adapter under the provider's own ID.
     *
     * @param int|string $key Registration key of the panel; a string key must match the provider's own ID.
     * @param PortablePanel $provider Declarative panel to adapt.
     *
     * @throws InvalidConfigException when the registration key contradicts the provider ID.
     *
     * @return ProviderPanel Adapter carrying the provider.
     */
    private function adaptProvider(int|string $key, PortablePanel $provider): ProviderPanel
    {
        try {
            EntryParser::assertKeyMatchesId($key, $provider->id(), 'panel');
        } catch (InvalidArgumentException $exception) {
            throw new InvalidConfigException(
                Message::PROVIDER_ID_MISMATCH->getMessage('panel'),
                0,
                $exception,
            );
        }

        return new ProviderPanel(['id' => $provider->id(), 'provider' => $provider]);
    }

    /**
     * Rejects a `title` or `icon` override on a panel that renders the metadata it declares itself.
     *
     * @param string $id Registration ID of the panel.
     * @param PanelOverride $override Registration options declared for that panel.
     *
     * @throws InvalidConfigException when the override declares a title or an icon.
     */
    private static function assertNoMetadataOverride(string $id, PanelOverride $override): void
    {
        if ($override->title !== null || $override->icon !== null) {
            throw new InvalidConfigException(
                Message::PANEL_METADATA_OVERRIDE_UNSUPPORTED->getMessage($id),
            );
        }
    }

    /**
     * Binds a resolved panel to the module and fires {@see Panel::moduleBound()} once the references are in place.
     *
     * @param Panel $panel Panel to bind.
     *
     * @throws InvalidConfigException when the panel rejects the module binding.
     *
     * @return Panel Bound panel.
     */
    private function bindPanel(Panel $panel): Panel
    {
        $panel->module = $this->module;

        $panel->moduleBound();

        return $panel;
    }

    /**
     * Resolves a panel registration into a {@see Panel} instance, binding `id` and `module` references and firing
     * {@see Panel::moduleBound()} once both references are in place.
     *
     * A class string or a `class` entry naming a portable {@see PortablePanel} is built through the container and
     * adapted by {@see ProviderPanel}, exactly as an already-instantiated provider is.
     *
     * @param int|string $key Registration key of the panel; a string key must match the provider's own ID.
     * @param array<string, mixed>|Panel|PortablePanel|string $config Panel or provider instance, configuration array,
     * or class-name string.
     *
     * @throws InvalidConfigException when the class name is unresolvable, the registration key contradicts the
     * provider ID, or the container returns an object outside the panel contract.
     *
     * @return Panel Resolved panel bound to the module.
     */
    private function buildPanel(int|string $key, Panel|PortablePanel|array|string $config): Panel
    {
        if ($config instanceof PortablePanel) {
            return $this->bindPanel($this->adaptProvider($key, $config));
        }

        if ($config instanceof Panel) {
            $config->id = (string) $key;

            return $this->bindPanel($config);
        }

        [$class, $properties] = ComponentResolver::classAndProperties($config);

        if ($class === null) {
            throw new InvalidConfigException(
                Message::PANEL_CLASS_INVALID->getMessage((string) $key),
            );
        }

        if (is_subclass_of($class, PortablePanel::class)) {
            $provider = Yii::$container->get($class, [], $properties);

            if (!$provider instanceof PortablePanel) {
                throw new InvalidConfigException(
                    Message::PANEL_INSTANCE_INVALID->getMessage((string) $key, PortablePanel::class, $class),
                );
            }

            return $this->bindPanel($this->adaptProvider($key, $provider));
        }

        $properties['module'] = $this->module;
        $properties['id'] = (string) $key;

        $object = Yii::$container->get($class, [], $properties);

        if (!$object instanceof Panel) {
            throw new InvalidConfigException(
                Message::PANEL_INSTANCE_INVALID->getMessage((string) $key, Panel::class, $class),
            );
        }

        return $this->bindPanel($object);
    }

    /**
     * Reads the registration options an array definition declares, without resolving its class.
     *
     * An entry disabling itself returns immediately, so a definition naming an uninstalled optional package never
     * reaches the autoloader. A portable definition accepts nothing beyond `class` and the registration options, so
     * any other key is rejected by name; a Yii panel definition keeps its remaining entries as component properties.
     *
     * @param array<array-key, mixed> $definition Panel definition declared by the application.
     *
     * @throws InvalidConfigException when an option is unknown, or carries an unsupported value.
     *
     * @return PanelOverride Registration options declared by the definition.
     */
    private static function panelOverride(array $definition): PanelOverride
    {
        if (($definition['enabled'] ?? null) === false) {
            return new PanelOverride(enabled: false);
        }

        [$class, $properties] = ComponentResolver::classAndProperties($definition);

        $options = $class !== null && is_subclass_of($class, PortablePanel::class)
            ? $properties
            : array_intersect_key($properties, array_flip(PanelOverride::KEYS));

        try {
            return PanelOverride::fromArray($options);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidConfigException(
                $exception->getMessage(),
                0,
                $exception,
            );
        }
    }

    /**
     * Resolves the effective panel catalog and orders the registered panels to match it.
     *
     * Defaults are read from the registered panels in registration order, so built-ins keep the order the module
     * declares and only the extensions are reordered by the shared policy. A panel registered under a built-in ID is a
     * built-in, whatever class the application configures for it; every other panel, and any {@see JsonPanel}, is an
     * extension. The resolved title and icon reach the
     * panels the host renders metadata for, and a panel declaring no name registers under its ID, which the policy
     * requires to be non-empty.
     *
     * @param array<string, PanelOverride> $overrides Registration options indexed by panel ID.
     * @param array<string, mixed> $core Built-in panel definitions indexed by panel ID.
     *
     * @throws InvalidConfigException when the declared metadata or a registration option is rejected by the policy.
     *
     * @return PanelCatalog Registered panels in display order and the catalog describing them.
     */
    private function resolveCatalog(array $overrides, array $core): PanelCatalog
    {
        try {
            $defaults = [];

            foreach ($this->module->panels as $id => $panel) {
                $name = $panel->getName();

                $title = $name === '' ? $id : $name;
                $icon = $panel->getToolbarIcon() ?? '';

                $defaults[] = isset($core[$id]) && !$panel instanceof JsonPanel
                    ? PanelRegistration::builtIn($id, $title, $icon)
                    : PanelRegistration::extension($id, $title, $icon);
            }

            $registry = PanelRegistry::resolve($defaults, $overrides);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidConfigException(
                $exception->getMessage(),
                0,
                $exception,
            );
        }

        $ordered = [];

        foreach ($registry->enabled() as $registration) {
            // Every enabled registration carries a registered panel key, so this guard is unreachable.
            // @infection-ignore-all
            $panel = $this->module->panels[$registration->id] ?? throw new InvalidConfigException(
                Message::DEBUG_PANEL_NOT_FOUND->getMessage($registration->id),
            );

            if ($panel instanceof ProviderPanel) {
                $panel->title = $registration->title;
                $panel->icon = $registration->icon === '' ? null : $registration->icon;
            }

            $ordered[$registration->id] = $panel;
        }

        return new PanelCatalog($ordered, $registry);
    }
}
