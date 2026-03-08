<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Commands;

use Illuminate\Console\Command;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Boost Install Command for Testbench/Monorepo environments
 *
 * Runs the standard boost:install command from the actual project root
 * so boost.json and guideline files are written to the correct location.
 */
#[AsCommand('commerce:boost-install', 'Install Laravel Boost guidelines for the monorepo project')]
final class BoostInstallCommand extends Command
{
    protected $description = 'Install Laravel Boost guidelines using the correct project root';

    public function handle(): int
    {
        $projectRoot = $this->getProjectRoot();
        $configPath = $projectRoot . '/boost.json';

        if (file_exists($configPath)) {
            $this->components->warn('boost.json already exists at project root; re-running boost:install.');
        }

        $this->components->info("Running boost:install in: {$projectRoot}");
        $this->newLine();

        $originalBasePath = base_path();
        $originalAppPath = app()->path();
        $originalNamespace = $this->getApplicationNamespace();
        $appPath = $this->getAppPath($projectRoot);
        $appNamespace = $this->getAppNamespace($appPath);

        app()->setBasePath($projectRoot);

        if ($appPath !== null) {
            app()->useAppPath($appPath);
        }

        if ($appNamespace !== null) {
            $this->setApplicationNamespace($appNamespace);
        }

        $this->ensureCustomGuidelinesSymlink($projectRoot);

        try {
            $exitCode = $this->call('boost:install');
        } finally {
            app()->setBasePath($originalBasePath);
            app()->useAppPath($originalAppPath);
            $this->setApplicationNamespace($originalNamespace);
        }

        return $exitCode === self::SUCCESS ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Get the actual project root directory.
     */
    private function getProjectRoot(): string
    {
        $cwd = getcwd();

        if (is_string($cwd) && file_exists($cwd . '/composer.json')) {
            return $cwd;
        }

        if (function_exists('\\Orchestra\\Testbench\\package_path')) {
            return \Orchestra\Testbench\package_path();
        }

        return base_path();
    }

    private function getAppPath(string $projectRoot): ?string
    {
        $direct = $projectRoot . '/app';

        if (is_dir($direct)) {
            return $direct;
        }

        $demoApp = $projectRoot . '/demo/app';

        if (is_dir($demoApp)) {
            return $demoApp;
        }

        return null;
    }

    private function getAppNamespace(?string $appPath): ?string
    {
        if ($appPath === null) {
            return null;
        }

        if (str_ends_with($appPath, '/demo/app')) {
            return 'App\\';
        }

        return null;
    }

    private function getApplicationNamespace(): ?string
    {
        $property = new ReflectionProperty(app(), 'namespace');
        $property->setAccessible(true);

        /** @var string|null $namespace */
        $namespace = $property->getValue(app());

        return $namespace;
    }

    private function setApplicationNamespace(?string $namespace): void
    {
        $property = new ReflectionProperty(app(), 'namespace');
        $property->setAccessible(true);
        $property->setValue(app(), $namespace);
    }

    /**
     * Ensure custom guidelines from project root are accessible via symlink.
     */
    private function ensureCustomGuidelinesSymlink(string $projectRoot): void
    {
        $sourceDir = $projectRoot . '/.ai/guidelines';
        $targetDir = base_path('.ai');
        $targetLink = $targetDir . '/guidelines';

        if (! is_dir($sourceDir)) {
            return;
        }

        if (is_link($targetLink) && readlink($targetLink) === $sourceDir) {
            return;
        }

        if (! is_dir($targetDir) && ! mkdir($targetDir, 0755, true)) {
            throw new RuntimeException("Failed to create directory: {$targetDir}");
        }

        if (is_link($targetLink)) {
            if (! unlink($targetLink)) {
                throw new RuntimeException("Failed to remove existing symlink: {$targetLink}");
            }
        } elseif (file_exists($targetLink)) {
            $this->components->warn("Skipping symlink creation; a real path already exists at: {$targetLink}");

            return;
        }

        if (! symlink($sourceDir, $targetLink)) {
            throw new RuntimeException("Failed to create symlink: {$targetLink}");
        }
    }
}
