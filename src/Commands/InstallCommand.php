<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Commands;

use AIArmada\CommerceSupport\Actions\DiscoverCommercePublishTagsAction;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

final class InstallCommand extends Command
{
    protected $signature = 'commerce:install
                            {--with-config : Also publish config files for detected Commerce packages}
                            {--all : Publish all detected publish tags}
                            {--tags=* : Publish only specific publish tags (e.g. cart-migrations)}
                            {--list : List available publish tags}
                            {--dry-run : Do not publish, only show what would be published}
                            {--force : Overwrite any existing published files}';

    protected $description = 'Install Commerce packages by publishing migrations (and optionally config)';

    public function handle(): int
    {
        $available = $this->publishTagsByProvider(
            includeConfig: (bool) $this->option('with-config'),
        );

        if ($available === []) {
            $this->components->warn('No Commerce publish tags detected.');

            return self::SUCCESS;
        }

        if ((bool) $this->option('list')) {
            $this->renderAvailable($available);

            return self::SUCCESS;
        }

        $selectedTags = $this->selectedTags($available);

        if ($selectedTags === []) {
            $this->components->warn('No tags selected.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        foreach ($selectedTags as $tag) {
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
    private function publishTagsByProvider(bool $includeConfig): array
    {
        return DiscoverCommercePublishTagsAction::run(includeConfig: $includeConfig);
    }

    /**
     * @param  array<class-string, array<int, string>>  $available
     */
    private function renderAvailable(array $available): void
    {
        $rows = [];

        foreach ($available as $provider => $tags) {
            foreach ($tags as $tag) {
                $rows[] = [$this->tagType($tag), $tag, $provider];
            }
        }

        $this->table(['Type', 'Tag', 'Provider'], $rows);
    }

    private function tagType(string $tag): string
    {
        if (str_ends_with($tag, '-migrations')) {
            return 'migrations';
        }

        if (str_ends_with($tag, '-config')) {
            return 'config';
        }

        return 'other';
    }

    /**
     * @param  array<class-string, array<int, string>>  $available
     * @return array<int, string>
     */
    private function selectedTags(array $available): array
    {
        $allTags = collect(Arr::flatten(array_values($available)))
            ->unique()
            ->values()
            ->all();

        /** @var array<int, string> $tagsOption */
        $tagsOption = $this->option('tags');

        if ($tagsOption !== []) {
            $unknown = array_values(array_diff($tagsOption, $allTags));

            if ($unknown !== []) {
                $this->components->error('Unknown publish tag(s): ' . implode(', ', $unknown));

                return [];
            }

            return $tagsOption;
        }

        if ((bool) $this->option('all')) {
            return $allTags;
        }

        if (! $this->input->isInteractive()) {
            $this->components->warn('Non-interactive session: use --all or --tags=...');

            return [];
        }

        $choices = array_merge(['<all>'], $allTags);

        /** @var array<int, string> $selected */
        $selected = $this->choice(
            'Which publish groups do you want to publish?',
            $choices,
            default: 0,
            multiple: true,
        );

        if (in_array('<all>', $selected, true)) {
            return $allTags;
        }

        return $selected;
    }
}
