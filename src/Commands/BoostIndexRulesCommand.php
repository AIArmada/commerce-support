<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Commands;

use AIArmada\CommerceSupport\Actions\ResolveProjectRootAction;
use AIArmada\CommerceSupport\Support\ProjectRuleRepository;
use Illuminate\Console\Command;
use Laravel\Boost\Rules\RuleRepository;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Boost Index Rules Command for Testbench/Monorepo environments
 *
 * The standard `boost:index-rules` command resolves `base_path()` to the
 * Testbench skeleton, so it looks for `.ai/rules` in the wrong directory.
 * This command regenerates the index from the actual project root.
 */
#[AsCommand('commerce:boost-index-rules', 'Regenerate the project rules index from .ai/rules')]
final class BoostIndexRulesCommand extends Command
{
    protected $description = 'Regenerate the Boost project rules index using the correct project root';

    public function handle(): int
    {
        if (! class_exists(RuleRepository::class)) {
            $this->components->error('Laravel Boost is not installed.');

            return self::FAILURE;
        }

        $projectRoot = ResolveProjectRootAction::run();
        $repository = new ProjectRuleRepository($projectRoot . '/.ai/rules', $projectRoot);

        if (! $repository->exists()) {
            $this->components->info('No project rules found in .ai/rules.');

            return self::SUCCESS;
        }

        $conflicted = $repository->conflictedFiles();

        if ($conflicted !== []) {
            $this->components->error('Resolve the merge conflicts in these rule files first:');

            foreach ($conflicted as $file) {
                $this->line('  - ' . $repository->relativePath($file));
            }

            return self::FAILURE;
        }

        foreach ($repository->unindexedFiles() as $file) {
            $this->components->warn('Skipped ' . $repository->relativePath($file) . ': no valid `paths` frontmatter.');
        }

        $path = $repository->writeIndex();

        $this->components->info('Regenerated ' . $repository->relativePath($path) . '.');

        return self::SUCCESS;
    }
}
