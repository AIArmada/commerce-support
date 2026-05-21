<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Actions;

use Illuminate\Support\ServiceProvider;
use Lorisleiva\Actions\Concerns\AsAction;

final class DiscoverCommerceMigrationPublishTagsAction
{
    use AsAction;

    /**
     * @return array<class-string, array<int, string>>
     */
    public function handle(): array
    {
        $migrationTags = array_values(array_filter(
            ServiceProvider::publishableGroups(),
            static fn (string $group): bool => str_ends_with($group, '-migrations')
        ));

        if ($migrationTags === []) {
            return [];
        }

        $result = [];

        /** @var array<int, class-string> $providers */
        $providers = ServiceProvider::publishableProviders();

        foreach ($providers as $providerClass) {
            if (! str_starts_with($providerClass, 'AIArmada\\')) {
                continue;
            }

            $tagsForProvider = [];

            foreach ($migrationTags as $tag) {
                $paths = ServiceProvider::pathsToPublish($providerClass, $tag);

                if ($paths !== []) {
                    $tagsForProvider[] = $tag;
                }
            }

            if ($tagsForProvider !== []) {
                $result[$providerClass] = array_values(array_unique($tagsForProvider));
            }
        }

        ksort($result);

        return $result;
    }
}
