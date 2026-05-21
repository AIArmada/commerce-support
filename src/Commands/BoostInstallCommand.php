<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Commands;

use AIArmada\CommerceSupport\Actions\EnsureCustomGuidelinesSymlinkAction;
use AIArmada\CommerceSupport\Actions\ResolveProjectRootAction;
use Illuminate\Console\Command;
use ReflectionProperty;
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
        $projectRoot = ResolveProjectRootAction::run();
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

        EnsureCustomGuidelinesSymlinkAction::run(
            projectRoot: $projectRoot,
            warn: function (string $message): void {
                $this->components->warn($message);
            },
        );

        try {
            $exitCode = $this->call('boost:install');
        } finally {
            app()->setBasePath($originalBasePath);
            app()->useAppPath($originalAppPath);
            $this->setApplicationNamespace($originalNamespace);
        }

        return $exitCode === self::SUCCESS ? self::SUCCESS : self::FAILURE;
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
}
