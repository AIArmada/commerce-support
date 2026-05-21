<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Commands;

use AIArmada\CommerceSupport\Actions\EnsureCustomGuidelinesSymlinkAction;
use AIArmada\CommerceSupport\Actions\ResolveProjectRootAction;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Laravel\Boost\Contracts\SupportsGuidelines;
use Laravel\Boost\Install\Agents\Agent;
use Laravel\Boost\Install\AgentsDetector;
use Laravel\Boost\Install\GuidelineComposer;
use Laravel\Boost\Install\GuidelineConfig;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Process\Process;

/**
 * Boost Update Command for Testbench/Monorepo environments
 *
 * This command updates Laravel Boost guidelines correctly in Orchestra Testbench
 * environments where `base_path()` points to the testbench skeleton directory
 * instead of the actual project root.
 *
 * The standard `boost:update` command fails in testbench because:
 * - It reads boost.json from vendor/orchestra/testbench-core/laravel/
 * - It writes guideline files (CLAUDE.md, AGENTS.md, etc.) to the wrong location
 *
 * This command manually reads the config from and writes guidelines to the actual
 * project root directory.
 */
#[AsCommand('commerce:boost-update', 'Update Laravel Boost guidelines for the monorepo project')]
final class BoostUpdateCommand extends Command
{
    protected $description = 'Update Laravel Boost guidelines using the correct project root';

    public function handle(AgentsDetector $detector): int
    {
        $projectRoot = ResolveProjectRootAction::run();
        $configPath = $projectRoot . '/boost.json';

        if (! file_exists($configPath)) {
            $this->components->error('No boost.json found at project root. Run commerce:boost-install first.');

            return self::FAILURE;
        }

        $this->components->info("Updating Boost guidelines for: {$projectRoot}");
        $this->newLine();

        EnsureCustomGuidelinesSymlinkAction::run(
            projectRoot: $projectRoot,
            warn: function (string $message): void {
                $this->components->warn($message);
            },
        );

        // Read config from actual project root
        $config = json_decode(file_get_contents($configPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->components->error('Invalid boost.json file.');

            return self::FAILURE;
        }

        $agentNames = $config['agents'] ?? [];
        $guidelineNames = $config['packages'] ?? [];

        if (isset($config['guidelines']) && is_array($config['guidelines'])) {
            $guidelineNames = $config['guidelines'];
        }

        if (empty($agentNames)) {
            $this->components->warn('No agents configured in boost.json.');

            return self::SUCCESS;
        }

        // Get agent instances
        $allAgents = $detector->getAgents();
        $availableAgents = $allAgents->filter(fn (Agent $agent): bool => $agent instanceof SupportsGuidelines);

        /** @var Collection<int, Agent&SupportsGuidelines> $selectedAgents */
        $selectedAgents = collect($agentNames)
            ->map(fn (string $name) => $availableAgents->first(fn (Agent $agent): bool => $agent->name() === $name))
            ->filter()
            ->values();

        if ($selectedAgents->isEmpty()) {
            $this->components->warn('No valid agents found for the configured names.');

            return self::SUCCESS;
        }

        // Compose guidelines
        $guidelineConfig = new GuidelineConfig;
        $guidelineConfig->enforceTests = $this->hasMinimumTests($projectRoot);
        $guidelineConfig->laravelStyle = false;
        $guidelineConfig->caresAboutLocalization = false;
        $guidelineConfig->hasAnApi = false;
        $guidelineConfig->aiGuidelines = $guidelineNames;
        $guidelineConfig->usesSail = $config['sail'] ?? false;

        $composer = app(GuidelineComposer::class)->config($guidelineConfig);
        $guidelines = $composer->guidelines();

        $this->components->info(sprintf('Adding %d guidelines to %d agents', $guidelines->count(), $selectedAgents->count()));
        $this->newLine();

        $composedGuidelines = $composer->compose();

        // Write guidelines to each agent
        $failed = [];

        /** @var Agent&SupportsGuidelines $agent */
        foreach ($selectedAgents as $agent) {
            $agentName = $agent->displayName();
            $this->output->write("  {$agentName}... ");

            try {
                $guidelinePath = $projectRoot . '/' . mb_ltrim($agent->guidelinesPath(), '/');
                $this->writeGuidelineFile($projectRoot, $guidelinePath, $composedGuidelines, $agent->frontmatter());
                $this->line('<fg=green>✓</>');
            } catch (Exception $e) {
                $failed[$agentName] = $e->getMessage();
                $this->line('<fg=red>✗</>');
            }
        }

        $this->newLine();

        if (! empty($failed)) {
            $this->components->error(sprintf('Failed to install guidelines to %d agent(s):', count($failed)));
            foreach ($failed as $agentName => $error) {
                $this->line("  - {$agentName}: {$error}");
            }

            return self::FAILURE;
        }

        $this->components->info('Boost guidelines updated successfully.');

        return self::SUCCESS;
    }

    /**
     * Write the guideline file with proper structure.
     */
    private function writeGuidelineFile(string $projectRoot, string $filePath, string $guidelines, bool $frontmatter): void
    {
        $directory = dirname($filePath);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true)) {
            throw new RuntimeException("Failed to create directory: {$directory}");
        }

        $realRoot = realpath($projectRoot);
        $realDirectory = realpath($directory);

        if ($realRoot === false || $realDirectory === false) {
            throw new RuntimeException('Failed to resolve project root or target directory path.');
        }

        $realRoot = mb_rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $realDirectory = mb_rtrim($realDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (! str_starts_with($realDirectory, $realRoot)) {
            throw new RuntimeException("Refusing to write outside project root: {$filePath}");
        }

        $handle = fopen($filePath, 'c+');

        if (! $handle) {
            throw new RuntimeException("Failed to open file: {$filePath}");
        }

        try {
            // Simple file locking
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException("Failed to acquire lock on file: {$filePath}");
            }

            $content = stream_get_contents($handle);
            $pattern = '/<laravel-boost-guidelines>.*?<\/laravel-boost-guidelines>/s';
            $replacement = "<laravel-boost-guidelines>\n" . $guidelines . "\n</laravel-boost-guidelines>";

            if (preg_match($pattern, $content)) {
                $newContent = preg_replace($pattern, $replacement, $content, 1);
            } else {
                $frontMatterStr = '';

                if ($frontmatter && ! str_contains($content, "\n---\n")) {
                    $frontMatterStr = "---\nalwaysApply: true\n---\n";
                }

                $existingContent = mb_rtrim($content);
                $separatingNewlines = empty($existingContent) ? '' : "\n\n===\n\n";
                $newContent = $frontMatterStr . $existingContent . $separatingNewlines . $replacement;
            }

            if (! str_ends_with((string) $newContent, "\n")) {
                $newContent .= "\n";
            }

            if (ftruncate($handle, 0) === false || fseek($handle, 0) === -1) {
                throw new RuntimeException("Failed to reset file pointer: {$filePath}");
            }

            if (fwrite($handle, (string) $newContent) === false) {
                throw new RuntimeException("Failed to write to file: {$filePath}");
            }

            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Check if the project has minimum test coverage.
     */
    private function hasMinimumTests(string $projectRoot): bool
    {
        if (! file_exists($projectRoot . '/vendor/bin/phpunit')) {
            return false;
        }

        $process = new Process([PHP_BINARY, 'vendor/bin/pest', '--list-tests'], $projectRoot);
        $process->run();

        $testCount = collect(explode("\n", $process->getOutput()))
            ->filter(fn ($line): bool => str_contains($line, '::'))
            ->count();

        return $testCount >= 6;
    }
}
