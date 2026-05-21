<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Actions;

use Illuminate\Support\ServiceProvider;
use Lorisleiva\Actions\Concerns\AsAction;

final class DiscoverCommercePublishTagsAction
{
    use AsAction;

    /**
     * @return array<class-string, array<int, string>>
     */
    public function handle(bool $includeConfig): array
    {
        $allowedSuffixes = ['-migrations'];

        if ($includeConfig) {
            $allowedSuffixes[] = '-config';
        }

        $candidateTags = array_values(array_filter(
            ServiceProvider::publishableGroups(),
            static function (string $group) use ($allowedSuffixes): bool {
                foreach ($allowedSuffixes as $suffix) {
                    if (str_ends_with($group, $suffix)) {
                        return true;
                    }
                }

                return false;
            }
        ));

        if ($candidateTags === []) {
            return [];
        }

        /** @var array<int, class-string> $providers */
        $providers = ServiceProvider::publishableProviders();

        $result = [];

        foreach ($providers as $providerClass) {
            if (! str_starts_with($providerClass, 'AIArmada\\')) {
                continue;
            }

            $tagsForProvider = [];

            foreach ($candidateTags as $tag) {
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
