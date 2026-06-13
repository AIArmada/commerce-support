<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support\Filament;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;
use Filament\Pages\PageConfiguration;
use Filament\Resources\Resource;
use Filament\Resources\ResourceConfiguration;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use UnitEnum;

final class CommerceNavigation
{
    /**
     * @return array<NavigationGroup>
     */
    public static function groups(): array
    {
        if (! self::enabled() || ! class_exists(NavigationGroup::class)) {
            return [];
        }

        $configGroups = self::navigationConfig('groups', []);
        $groups = [];

        if (is_array($configGroups)) {
            $groups = $configGroups;
        } else {
            $groups = [];
        }

        // Auto-discover group names from panel resources/pages so ALL sidebar
        // groups get a NavigationGroup with config sort values, not just the
        // ones explicitly defined in config.
        try {
            $panel = Filament::getCurrentOrDefaultPanel();
        } catch (\Throwable) {
            $panel = null;
        }

        if ($panel !== null) {
            $extractGroup = static function (string $class) use (&$groups): void {
                if (! method_exists($class, 'getNavigationGroup')) {
                    return;
                }
                $g = $class::getNavigationGroup();
                if (! is_string($g) || $g === '' || array_key_exists($g, $groups)) {
                    return;
                }
                $groups[$g] = ['label' => $g, 'collapsible' => true];
            };
            foreach ($panel->getResources() as $resource) {
                $extractGroup(is_string($resource) ? $resource : $resource::class);
            }
            foreach ($panel->getPages() as $page) {
                $extractGroup(is_string($page) ? $page : $page::class);
            }
            foreach ($panel->getPageConfigurations() as $configuration) {
                $page = $configuration->getPage();
                $extractGroup(is_string($page) ? $page : get_class($page));
            }
        }

        return collect($groups)
            ->sortBy(static function (mixed $definition, string | int $key): array {
                $sort = is_array($definition) && is_numeric($definition['sort'] ?? null)
                    ? (int) $definition['sort']
                    : 0;
                $label = is_array($definition) ? ($definition['label'] ?? $key) : (is_string($definition) ? $definition : $key);

                return [$sort, mb_strtolower($label)];
            })
            ->map(static function (mixed $definition, string | int $key): ?NavigationGroup {
                if (is_string($definition)) {
                    return NavigationGroup::make($definition);
                }

                if (! is_array($definition)) {
                    return null;
                }

                $label = $definition['label'] ?? $key;

                if (! is_string($label) || $label === '') {
                    return null;
                }

                $group = NavigationGroup::make($label);

                if (array_key_exists('icon', $definition) && is_string($definition['icon'])) {
                    $group->icon($definition['icon']);
                }

                if (array_key_exists('collapsible', $definition)) {
                    $group->collapsible((bool) $definition['collapsible']);
                }

                if (array_key_exists('collapsed', $definition)) {
                    $group->collapsed((bool) $definition['collapsed']);
                }

                return $group;
            })
            ->filter()
            ->all();
    }

    public static function configurePanel(object $panel): object
    {
        if (! self::enabled()) {
            return $panel;
        }

        // Guard against double-call from both CommerceNavigationPlugin and
        // FilamentCommerceSupportPlugin. Config is request-scoped in both
        // traditional PHP and Octane, unlike a static property.
        if (self::navigationConfig('_panel_configured', false)) {
            return $panel;
        }
        config()->set('commerce-support.filament.navigation._panel_configured', true);

        if (method_exists($panel, 'navigation')) {
            $panel->navigation(static fn (): NavigationBuilder => self::builder());
        }

        return $panel;
    }

    public static function builder(): NavigationBuilder
    {
        $panel = Filament::getCurrentOrDefaultPanel();
        $registeredGroups = [
            ...$panel->getNavigationGroups(),
            ...self::groups(),
        ];

        $items = collect([
            ...$panel->getNavigationItems(),
            ...self::pageNavigationItems($panel->getPages()),
            ...self::pageConfigurationNavigationItems($panel->getPageConfigurations()),
            ...self::resourceNavigationItems($panel->getResources()),
            ...self::resourceConfigurationNavigationItems($panel->getResourceConfigurations()),
        ])
            ->filter(fn (NavigationItem $item): bool => $item->isVisible())
            ->sortBy(fn (NavigationItem $item): int => $item->getSort())
            ->values();

        $groups = collect(self::groupNavigationItems($items, $registeredGroups))
            ->map(fn (NavigationGroup $g): NavigationGroup => $g->collapsible(true))
            ->all();

        return (new NavigationBuilder)
            ->groups($groups);
    }

    public static function configureNavigationItem(NavigationItem $item, string $component): NavigationItem
    {
        if (! self::enabled()) {
            return $item;
        }

        $item->visible($item->isVisible() && self::visible($component));
        $item->label(self::label($component, $item->getLabel()));

        $effectiveGroup = self::group($component, $item->getGroup());
        $item->group($effectiveGroup);

        // If the item's group is hidden, hide the item too so the entire
        // group (header + all its items) disappears from the sidebar.
        if ($effectiveGroup !== null && $effectiveGroup !== '') {
            $groupConfig = self::navigationConfig("groups.{$effectiveGroup}", []);
            if (is_array($groupConfig) && ! empty($groupConfig['hidden'])) {
                $item->visible(false);
            }
        }

        $item->parentItem(self::parentItem($component, $item->getParentItem()));
        $item->sort(self::sort($component, $item->getSort()));

        return $item;
    }

    public static function visible(string $component, bool $default = true, ?string $package = null, ?string $item = null): bool
    {
        if (! self::enabled()) {
            return $default;
        }

        $config = self::itemConfig($component, $package, $item);

        if (array_key_exists('hidden', $config)) {
            return $default && ! (bool) $config['hidden'];
        }

        foreach (['visible', 'register'] as $key) {
            if (array_key_exists($key, $config)) {
                return $default && (bool) $config[$key];
            }
        }

        return $default;
    }

    public static function group(string $component, string | UnitEnum | null $default = null, ?string $package = null, ?string $item = null): string | UnitEnum | null
    {
        if (! self::enabled()) {
            return $default;
        }

        $config = self::itemConfig($component, $package, $item);

        if (! array_key_exists('group', $config)) {
            return $default;
        }

        $group = $config['group'];

        return is_string($group) || $group instanceof UnitEnum ? $group : $default;
    }

    public static function parentItem(string $component, ?string $default = null, ?string $package = null, ?string $item = null): ?string
    {
        if (! self::enabled()) {
            return $default;
        }

        $config = self::itemConfig($component, $package, $item);

        if (! array_key_exists('parent_item', $config)) {
            return $default;
        }

        return is_string($config['parent_item']) ? $config['parent_item'] : $default;
    }

    public static function sort(string $component, ?int $default = null, ?string $package = null, ?string $item = null): ?int
    {
        if (! self::enabled()) {
            return $default;
        }

        $config = self::itemConfig($component, $package, $item);

        if (! array_key_exists('sort', $config) || ! is_numeric($config['sort'])) {
            return $default;
        }

        return (int) $config['sort'];
    }

    public static function label(string $component, ?string $default = null, ?string $package = null, ?string $item = null): ?string
    {
        if (! self::enabled()) {
            return $default;
        }

        $config = self::itemConfig($component, $package, $item);

        if (! array_key_exists('label', $config)) {
            return $default;
        }

        $label = $config['label'];

        return is_string($label) && $label !== '' ? $label : $default;
    }

    private static function enabled(): bool
    {
        return (bool) self::navigationConfig('enabled', true);
    }

    /**
     * @param  array<int, class-string<Page>>  $pages
     * @return array<int, NavigationItem>
     */
    private static function pageNavigationItems(array $pages): array
    {
        $items = [];

        foreach ($pages as $page) {
            if (filled($page::getCluster())) {
                continue;
            }

            if (! $page::shouldRegisterNavigation()) {
                continue;
            }

            if (! $page::canAccess()) {
                continue;
            }

            if (! self::visible($page)) {
                continue;
            }

            foreach ($page::getNavigationItems() as $item) {
                $items[] = self::configureNavigationItem($item, $page);
            }
        }

        return $items;
    }

    /**
     * @param  array<int, PageConfiguration>  $configurations
     * @return array<int, NavigationItem>
     */
    private static function pageConfigurationNavigationItems(array $configurations): array
    {
        $items = [];

        foreach ($configurations as $configuration) {
            Filament::setCurrentPageConfigurationKey($configuration->getKey());

            try {
                $items = [
                    ...$items,
                    ...self::pageNavigationItems([$configuration->getPage()]),
                ];
            } finally {
                Filament::setCurrentPageConfigurationKey(null);
            }
        }

        return $items;
    }

    /**
     * @param  array<int, class-string<resource>>  $resources
     * @return array<int, NavigationItem>
     */
    private static function resourceNavigationItems(array $resources): array
    {
        $items = [];

        foreach ($resources as $resource) {
            if (filled($resource::getCluster())) {
                continue;
            }

            if ($resource::getParentResourceRegistration()) {
                continue;
            }

            if (! $resource::shouldRegisterNavigation()) {
                continue;
            }

            if (! $resource::canAccess()) {
                continue;
            }

            if (! self::visible($resource)) {
                continue;
            }

            foreach ($resource::getNavigationItems() as $item) {
                $items[] = self::configureNavigationItem($item, $resource);
            }
        }

        return $items;
    }

    /**
     * @param  array<int, ResourceConfiguration>  $configurations
     * @return array<int, NavigationItem>
     */
    private static function resourceConfigurationNavigationItems(array $configurations): array
    {
        $items = [];

        foreach ($configurations as $configuration) {
            Filament::setCurrentResourceConfigurationKey($configuration->getKey());

            try {
                $items = [
                    ...$items,
                    ...self::resourceNavigationItems([$configuration->getResource()]),
                ];
            } finally {
                Filament::setCurrentResourceConfigurationKey(null);
            }
        }

        return $items;
    }

    /**
     * @param  Collection<int, NavigationItem>  $items
     * @param  array<string | int, NavigationGroup | string>  $registeredGroups
     * @return array<NavigationGroup>
     */
    private static function groupNavigationItems(Collection $items, array $registeredGroups): array
    {
        $groups = collect($registeredGroups);

        $result = $items
            ->groupBy(static fn (NavigationItem $item): string => serialize($item->getGroup()))
            ->reduce(function (array $carry, Collection $itemGroup, string $groupIndex) use ($groups): array {
                $parentItems = $itemGroup->groupBy(static fn (NavigationItem $item): string => $item->getParentItem() ?? '');

                $groupItems = $parentItems->get('', collect())
                    ->keyBy(static fn (NavigationItem $item): string => $item->getLabel());

                $parentItems->except([''])->each(function (Collection $parentItemItems, string $parentItemLabel) use ($groupItems): void {
                    if (! $groupItems->has($parentItemLabel)) {
                        return;
                    }

                    $groupItems->get($parentItemLabel)->childItems($parentItemItems);
                });

                $groupItems = $groupItems->filter(static fn (NavigationItem $item): bool => filled($item->getChildItems()) || filled($item->getUrl()));
                $groupName = unserialize($groupIndex);
                $groupEnum = null;

                if ($groupName instanceof UnitEnum) {
                    $groupEnum = $groupName;
                    $groupName = $groupEnum->name;
                }

                if (blank($groupName)) {
                    $carry[] = NavigationGroup::make()->items($groupItems->values()->all());

                    return $carry;
                }

                $registeredGroup = $groups->first(static function (NavigationGroup | string $registeredGroup, string | int $registeredGroupIndex) use ($groupName): bool {
                    if ($registeredGroupIndex === $groupName) {
                        return true;
                    }

                    if ($registeredGroup === $groupName) {
                        return true;
                    }

                    if (! $registeredGroup instanceof NavigationGroup) {
                        return false;
                    }

                    return $registeredGroup->getLabel() === $groupName;
                });

                $group = null;

                if ($registeredGroup instanceof NavigationGroup) {
                    // Same registered group may match multiple group-by buckets
                    // (e.g. items with group "Events" and items with override
                    // group "My Events Wj" both match the same NavigationGroup).
                    // Merge items into an existing group with the same label
                    // instead of creating a duplicate sidebar header.
                    $label = $registeredGroup->getLabel() ?? "\0";
                    foreach ($carry as $existing) {
                        if (($existing->getLabel() ?? "\0") === $label) {
                            $existing->items([...$existing->getItems(), ...$groupItems->values()->all()]);

                            return $carry;
                        }
                    }

                    $group = $registeredGroup->items($groupItems->values()->all());
                } else {
                    $group = NavigationGroup::make($registeredGroup ?? $groupName);

                    if ($groupEnum instanceof HasLabel) {
                        $group->label($groupEnum->getLabel());
                    }

                    if ($groupEnum instanceof HasIcon) {
                        $group->icon($groupEnum->getIcon());
                    }

                    $group->items($groupItems->values()->all());
                }

                $carry[] = $group;

                return $carry;
            }, []);

        $result = collect($result)
            ->filter(static fn (NavigationGroup $group): bool => filled($group->getItems()))
            ->sortBy(fn (NavigationGroup $group, ?string $groupIndex): int => self::groupSort($group, $groupIndex, $registeredGroups))
            ->values()
            ->all();

        return $result;
    }

    /**
     * @param  array<string | int, NavigationGroup | string>  $registeredGroups
     */
    private static function groupSort(NavigationGroup $group, ?string $groupIndex, array $registeredGroups): int
    {
        if (blank($group->getLabel())) {
            return -1;
        }

        // $groupIndex may be a numeric key (from reduce) or a serialized
        // string (from map). When it's numeric, fall back to the label.
        $groupName = $group->getLabel();

        if ($groupIndex !== null && ! is_numeric($groupIndex)) {
            try {
                $unserialized = unserialize($groupIndex);
                if (is_string($unserialized) || $unserialized instanceof UnitEnum) {
                    $groupName = $unserialized;
                }
            } catch (\Throwable) {
                // Not a valid serialized value; fall back to the label.
            }
        }

        if ($groupName instanceof UnitEnum) {
            $groupName = $groupName->name;
        }
        $sort = array_search($groupName, self::groupSearchTokens($registeredGroups), true);

        if ($sort === false) {
            return count($registeredGroups);
        }

        return (int) $sort;
    }

    /**
     * @param  array<string | int, NavigationGroup | string>  $registeredGroups
     * @return array<int, string>
     */
    private static function groupSearchTokens(array $registeredGroups): array
    {
        $tokens = [];

        foreach ($registeredGroups as $key => $registeredGroup) {
            if (is_string($key)) {
                $tokens[] = $key;
            }

            if (is_string($registeredGroup)) {
                $tokens[] = $registeredGroup;

                continue;
            }

            $label = $registeredGroup->getLabel();

            if (is_string($label)) {
                $tokens[] = $label;
            }
        }

        return $tokens;
    }

    /**
     * @return array<string, mixed>
     */
    private static function itemConfig(string $component, ?string $package = null, ?string $item = null): array
    {
        $package ??= self::packageFromComponent($component);
        $item ??= self::itemFromComponent($component);

        return [
            ...self::packageConfig($package),
            ...self::packageItemConfig($package, $item),
            ...self::componentConfig($component),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function packageConfig(?string $package): array
    {
        if ($package === null) {
            return [];
        }

        $config = self::navigationConfig("packages.{$package}", []);

        return is_array($config) ? Arr::except($config, ['items']) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function packageItemConfig(?string $package, ?string $item): array
    {
        if ($package === null || $item === null) {
            return [];
        }

        $items = self::navigationConfig("packages.{$package}.items", []);

        if (! is_array($items)) {
            return [];
        }

        foreach (self::itemKeys($item) as $key) {
            $config = $items[$key] ?? null;

            if (is_array($config)) {
                return $config;
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function componentConfig(string $component): array
    {
        $items = self::navigationConfig('items', []);

        if (! is_array($items)) {
            return [];
        }

        foreach ([$component, mb_ltrim($component, '\\')] as $key) {
            $config = $items[$key] ?? null;

            if (is_array($config)) {
                return $config;
            }
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private static function itemKeys(string $item): array
    {
        return array_values(array_unique([
            $item,
            Str::plural($item),
            Str::singular($item),
        ]));
    }

    private static function packageFromComponent(string $component): ?string
    {
        if (! preg_match('/^AIArmada\\\\Filament([^\\\\]+)\\\\/', mb_ltrim($component, '\\'), $matches)) {
            return null;
        }

        return 'filament-' . Str::of($matches[1])->snake('-')->lower();
    }

    private static function itemFromComponent(string $component): ?string
    {
        $item = Str::of(class_basename($component))
            ->beforeLast('Resource')
            ->beforeLast('Page')
            ->snake()
            ->toString();

        return $item !== '' ? $item : null;
    }

    private static function navigationConfig(string $key, mixed $default = null): mixed
    {
        return config("commerce-support.filament.navigation.{$key}", $default);
    }
}
