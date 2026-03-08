<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

final class CommerceInstallCommand extends Command
{
    protected $signature = 'commerce:install
                            {--config : Also publish config files}
                            {--list : List available publish tags}
                            {--dry-run : Do not publish, only show what would be published}
                            {--force : Overwrite any existing published files}';

    protected $description = 'Install Commerce package resources (publish migrations, optionally configs)';

    public function handle(): int
    {
        $availableMigrations = $this->publishTagsByProvider('-migrations');
        $availableConfigs = $this->publishTagsByProvider('-config');

        if ((bool) $this->option('list')) {
            $this->renderAvailable($availableMigrations, $availableConfigs);

            return self::SUCCESS;
        }

        $publishConfigs = (bool) $this->option('config');

        if (! $publishConfigs && $this->input->isInteractive() && $availableConfigs !== []) {
            $publishConfigs = (bool) $this->confirm('Also publish config files?', false);
        }

        $tagsToPublish = $this->allTags($availableMigrations);

        if ($publishConfigs) {
            $tagsToPublish = array_values(array_unique(array_merge(
                $tagsToPublish,
                $this->allTags($availableConfigs)
            )));
        }

        sort($tagsToPublish);

        if ($tagsToPublish === []) {
            $this->components->warn('No publishable Commerce tags detected.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        foreach ($tagsToPublish as $tag) {
            if ($dryRun) {
                $this->line("Would publish: {$tag}");

                continue;
            }

            $this->components->info("Publishing: {$tag}");

            $exitCode = $this->call('vendor:publish', array_filter([
                '--tag' => $tag,
                '--force' => $force ? true : null,
            ]));

            if ($exitCode !== self::SUCCESS) {
                $this->components->error("Failed publishing tag: {$tag}");

                return $exitCode;
            }
        }

        if ($dryRun) {
            $this->components->info('Dry run complete.');
        } else {
            $this->components->info('Commerce install complete.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<class-string, array<int, string>>
     */
    private function publishTagsByProvider(string $suffix): array
    {
        $tags = array_values(array_filter(
            ServiceProvider::publishableGroups(),
            static fn (string $group): bool => str_ends_with($group, $suffix)
        ));

        if ($tags === []) {
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

            foreach ($tags as $tag) {
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

    /**
     * @param  array<class-string, array<int, string>>  $available
     * @return array<int, string>
     */
    private function allTags(array $available): array
    {
        return collect(Arr::flatten(array_values($available)))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<class-string, array<int, string>>  $availableMigrations
     * @param  array<class-string, array<int, string>>  $availableConfigs
     */
    private function renderAvailable(array $availableMigrations, array $availableConfigs): void
    {
        $rows = [];

        foreach ($availableMigrations as $provider => $tags) {
            foreach ($tags as $tag) {
                $rows[] = [$tag, 'migrations', $provider];
            }
        }

        foreach ($availableConfigs as $provider => $tags) {
            foreach ($tags as $tag) {
                $rows[] = [$tag, 'config', $provider];
            }
        }

        if ($rows === []) {
            $this->components->warn('No Commerce publish tags detected.');

            return;
        }

        usort($rows, static fn (array $a, array $b): int => [$a[1], $a[0]] <=> [$b[1], $b[0]]);

        $this->table(['Tag', 'Type', 'Provider'], $rows);
    }
}
