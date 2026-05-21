<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Commands;

use AIArmada\CommerceSupport\Actions\DiscoverCommerceMigrationPublishTagsAction;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

final class PublishMigrationsCommand extends Command
{
    protected $signature = 'commerce:publish-migrations
                            {--all : Publish migrations for all detected Commerce packages}
                            {--tags=* : Publish only specific publish tags (e.g. affiliates-migrations)}
                            {--list : List available migration publish tags}
                            {--dry-run : Do not publish, only show what would be published}
                            {--force : Overwrite any existing published files}';

    protected $description = 'Publish migration files for installed Commerce packages (preserving original timestamps)';

    public function handle(Filesystem $files): int
    {
        $available = $this->migrationPublishTagsByProvider();

        if ($available === []) {
            $this->components->warn('No Commerce migration publish tags detected.');

            return self::SUCCESS;
        }

        if ((bool) $this->option('list')) {
            $this->renderAvailable($available);

            return self::SUCCESS;
        }

        $selectedTags = $this->selectedTags($available);

        if ($selectedTags === []) {
            $this->components->warn('No migration tags selected.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $migrationPath = database_path('migrations');

        // Ensure migrations directory exists
        if (! $dryRun && ! $files->isDirectory($migrationPath)) {
            $files->makeDirectory($migrationPath, 0755, true);
        }

        $publishedCount = 0;
        $skippedCount = 0;

        foreach ($selectedTags as $tag) {
            $this->components->info("Publishing: {$tag}");

            // Get all paths to publish for this tag
            $paths = $this->getPathsForTag($tag, $available);

            foreach ($paths as $sourcePath => $destinationPath) {
                // Handle both files and directories
                if ($files->isDirectory($sourcePath)) {
                    // Source is a directory, get all migration files from it
                    $migrationFiles = $files->glob($sourcePath . '/*.php');

                    foreach ($migrationFiles as $sourceFile) {
                        $result = $this->copyMigrationFile($files, $sourceFile, $migrationPath, $force, $dryRun);
                        if ($result === 'published') {
                            $publishedCount++;
                        } elseif ($result === 'skipped') {
                            $skippedCount++;
                        }
                    }
                } else {
                    // Source is a file
                    $result = $this->copyMigrationFile($files, $sourcePath, $migrationPath, $force, $dryRun);
                    if ($result === 'published') {
                        $publishedCount++;
                    } elseif ($result === 'skipped') {
                        $skippedCount++;
                    }
                }
            }
        }

        if ($dryRun) {
            $this->components->info("Dry run complete. Would publish {$publishedCount} files.");
        } else {
            $this->components->info("Migration publishing complete. Published {$publishedCount} files, skipped {$skippedCount} existing.");
        }

        return self::SUCCESS;
    }

    /**
     * Copy a single migration file preserving its original filename.
     */
    private function copyMigrationFile(
        Filesystem $files,
        string $sourceFile,
        string $migrationPath,
        bool $force,
        bool $dryRun
    ): string {
        $sourceFileName = basename($sourceFile);
        $targetFile = $migrationPath . '/' . $sourceFileName;

        if ($files->exists($targetFile) && ! $force) {
            $this->components->twoColumnDetail(
                "Skipping file [{$sourceFileName}]",
                '<fg=yellow;options=bold>EXISTS</>'
            );

            return 'skipped';
        }

        if ($dryRun) {
            $this->components->twoColumnDetail(
                "Would copy [{$sourceFileName}]",
                '<fg=blue;options=bold>DRY-RUN</>'
            );

            return 'published';
        }

        $files->copy($sourceFile, $targetFile);

        $this->components->twoColumnDetail(
            "Copying file [{$sourceFileName}]",
            '<fg=green;options=bold>DONE</>'
        );

        return 'published';
    }

    /**
     * Get all source => destination paths for a specific tag.
     *
     * @param  array<class-string, array<int, string>>  $available
     * @return array<string, string>
     */
    private function getPathsForTag(string $tag, array $available): array
    {
        $allPaths = [];

        foreach ($available as $providerClass => $tags) {
            if (! in_array($tag, $tags, true)) {
                continue;
            }

            $paths = ServiceProvider::pathsToPublish($providerClass, $tag);
            $allPaths = array_merge($allPaths, $paths);
        }

        return $allPaths;
    }

    /**
     * @return array<class-string, array<int, string>>
     */
    private function migrationPublishTagsByProvider(): array
    {
        return DiscoverCommerceMigrationPublishTagsAction::run();
    }

    /**
     * @param  array<class-string, array<int, string>>  $available
     */
    private function renderAvailable(array $available): void
    {
        $rows = [];

        foreach ($available as $provider => $tags) {
            foreach ($tags as $tag) {
                $rows[] = [$tag, $provider];
            }
        }

        $this->table(['Tag', 'Provider'], $rows);
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
                $this->components->error('Unknown migration publish tag(s): ' . implode(', ', $unknown));

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
            'Which migration groups do you want to publish?',
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
