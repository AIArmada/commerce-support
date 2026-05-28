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

        $groups = self::navigationConfig('groups', []);

        if (! is_array($groups)) {
            return [];
        }

        return collect($groups)
            ->sortBy(static fn (mixed $definition): int => is_array($definition) && is_numeric($definition['sort'] ?? null)
                ? (int) $definition['sort']
                : 0)
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
            ->values()
            ->all();
    }

    public static function configurePanel(object $panel): object
    {
        if (! self::enabled()) {
            return $panel;
        }

        if (method_exists($panel, 'navigationGroups')) {
            $groups = self::groups();

            if ($groups !== []) {
                $panel->navigationGroups($groups);
            }
        }

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

        return (new NavigationBuilder)
            ->groups(self::groupNavigationItems($items, $registeredGroups));
    }

    public static function configureNavigationItem(NavigationItem $item, string $component): NavigationItem
    {
        if (! self::enabled()) {
            return $item;
        }

        $item->visible($item->isVisible() && self::visible($component));
        $item->group(self::group($component, $item->getGroup()));
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

        return $items
            ->groupBy(static fn (NavigationItem $item): string => serialize($item->getGroup()))
            ->map(function (Collection $items, string $groupIndex) use ($groups): NavigationGroup {
                $parentItems = $items->groupBy(static fn (NavigationItem $item): string => $item->getParentItem() ?? '');

                $items = $parentItems->get('', collect())
                    ->keyBy(static fn (NavigationItem $item): string => $item->getLabel());

                $parentItems->except([''])->each(function (Collection $parentItemItems, string $parentItemLabel) use ($items): void {
                    if (! $items->has($parentItemLabel)) {
                        return;
                    }

                    $items->get($parentItemLabel)->childItems($parentItemItems);
                });

                $items = $items->filter(static fn (NavigationItem $item): bool => filled($item->getChildItems()) || filled($item->getUrl()));
                $groupName = unserialize($groupIndex);
                $groupEnum = null;

                if ($groupName instanceof UnitEnum) {
                    $groupEnum = $groupName;
                    $groupName = $groupEnum->name;
                }

                if (blank($groupName)) {
                    return NavigationGroup::make()->items($items);
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

                if ($registeredGroup instanceof NavigationGroup) {
                    return $registeredGroup->items($items);
                }

                $group = NavigationGroup::make($registeredGroup ?? $groupName);

                if ($groupEnum instanceof HasLabel) {
                    $group->label($groupEnum->getLabel());
                }

                if ($groupEnum instanceof HasIcon) {
                    $group->icon($groupEnum->getIcon());
                }

                return $group->items($items);
            })
            ->filter(static fn (NavigationGroup $group): bool => filled($group->getItems()))
            ->sortBy(fn (NavigationGroup $group, ?string $groupIndex): int => self::groupSort($group, $groupIndex, $registeredGroups))
            ->values()
            ->all();
    }

    /**
     * @param  array<string | int, NavigationGroup | string>  $registeredGroups
     */
    private static function groupSort(NavigationGroup $group, ?string $groupIndex, array $registeredGroups): int
    {
        if (blank($group->getLabel())) {
            return -1;
        }

        $groupName = $groupIndex !== null ? unserialize($groupIndex) : $group->getLabel();

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
