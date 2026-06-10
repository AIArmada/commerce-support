<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectResolverInterface;
use AIArmada\CommerceSupport\Support\ConditionalMigrationLoader;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\Support\Payment\GuestPaymentSubjectDriver;
use AIArmada\CommerceSupport\Support\Payment\PaymentSubjectResolver;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingEngineInterface;
use AIArmada\CommerceSupport\Targeting\TargetingEngine;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use OwenIt\Auditing\AuditingServiceProvider;
use ReflectionClass;
use RuntimeException;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\Tags\TagsServiceProvider;
use Spatie\WebhookClient\WebhookClientServiceProvider;

/**
 * Support Service Provider
 *
 * Foundation service provider for all AIArmada Commerce packages.
 * Provides core helper methods, utilities, and base functionality.
 */
final class SupportServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('commerce-support')
            ->hasConfigFile('commerce-support')
            ->hasViews('commerce-support')
            ->hasCommands([
                Commands\SetupCommand::class,
                Commands\BoostInstallCommand::class,
                Commands\BoostUpdateCommand::class,
                Commands\PublishMigrationsCommand::class,
                Commands\InstallCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->registerOwnerResolver();
        $this->registerPaymentSubjectResolver();
        $this->registerTargetingEngine();
    }

    public function bootingPackage(): void
    {
        $this->loadDependencyMigrations();
        $this->validateMorphKeyType();
        $this->ensureOwnerResolverIsConfiguredWhenOwnerModeEnabled();
    }

    private function loadDependencyMigrations(): void
    {
        $settingsMigrationPath = $this->resolveDependencyPath(
            LaravelSettingsServiceProvider::class,
            'database/migrations/create_settings_table.php.stub',
            'vendor/spatie/laravel-settings/database/migrations/create_settings_table.php.stub'
        );

        if ($settingsMigrationPath !== null && ! $this->tableExists('settings')) {
            ConditionalMigrationLoader::loadFileIfMissing(
                $this,
                $settingsMigrationPath
            );
        }

        $auditsMigrationPath = $this->resolveCommerceSupportAuditMigrationPath()
            ?? $this->resolveDependencyPath(
                AuditingServiceProvider::class,
                'database/migrations/audits.stub',
                'vendor/owen-it/laravel-auditing/database/migrations/audits.stub'
            );

        if ($auditsMigrationPath !== null && ! $this->auditTableExists()) {
            ConditionalMigrationLoader::loadFileIfMissing(
                $this,
                $auditsMigrationPath,
                'create_audits_table'
            );
        }

        $auditsUserActorFixPath = dirname(__DIR__) . '/database/migrations/1970_01_01_000003_fix_audits_user_actor_column_type.php.stub';

        if (is_file($auditsUserActorFixPath)) {
            ConditionalMigrationLoader::loadFileIfMissing(
                $this,
                $auditsUserActorFixPath,
                'fix_audits_user_actor_column_type'
            );
        }

        $activityLogMigrationsPath = $this->resolveDependencyPath(
            ActivitylogServiceProvider::class,
            'database/migrations',
            'vendor/spatie/laravel-activitylog/database/migrations'
        );

        if ($activityLogMigrationsPath !== null && ! $this->tableExists('activity_log')) {
            ConditionalMigrationLoader::loadDirectoryIfMissing(
                $this,
                $activityLogMigrationsPath
            );
        }

        $tagsMigrationPath = $this->resolveDependencyPath(
            TagsServiceProvider::class,
            'database/migrations/create_tag_tables.php.stub',
            'vendor/spatie/laravel-tags/database/migrations/create_tag_tables.php.stub'
        );

        if ($tagsMigrationPath !== null && ! $this->tagTablesExist()) {
            ConditionalMigrationLoader::loadFileIfMissing(
                $this,
                $tagsMigrationPath
            );
        }

        $mediaMigrationPath = $this->resolveDependencyPath(
            MediaLibraryServiceProvider::class,
            'database/migrations/create_media_table.php.stub',
            'vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub'
        );

        if ($mediaMigrationPath !== null && ! $this->tableExists('media')) {
            ConditionalMigrationLoader::loadFileIfMissing(
                $this,
                $mediaMigrationPath
            );
        }

        if ($this->shouldLoadWebhookCallsMigration()) {
            $webhookCallsMigrationPath = dirname(__DIR__) . '/database/migrations/1970_01_01_000004_create_webhook_calls_table.php.stub';

            if (is_file($webhookCallsMigrationPath)) {
                ConditionalMigrationLoader::loadFileIfMissing(
                    $this,
                    $webhookCallsMigrationPath,
                    'create_webhook_calls_table'
                );
            }
        }

        if ($this->tableExists('webhook_calls')) {
            $webhookLifecycleMigrationPath = dirname(__DIR__) . '/database/migrations/1970_01_01_000005_add_webhook_lifecycle_columns.php.stub';

            if (is_file($webhookLifecycleMigrationPath)) {
                ConditionalMigrationLoader::loadFileIfMissing(
                    $this,
                    $webhookLifecycleMigrationPath,
                    'add_webhook_lifecycle_columns'
                );
            }
        }
    }

    private function shouldLoadWebhookCallsMigration(): bool
    {
        if (! class_exists(WebhookClientServiceProvider::class)) {
            return false;
        }

        if ($this->tableExists('webhook_calls')) {
            return false;
        }

        $configs = config('webhook-client.configs', []);

        if (! is_array($configs)) {
            return false;
        }

        foreach ($configs as $config) {
            if (! is_array($config)) {
                continue;
            }

            $name = $config['name'] ?? null;
            $webhookModel = $config['webhook_model'] ?? null;
            $processWebhookJob = $config['process_webhook_job'] ?? null;

            if (is_string($name) && $name !== ''
                && is_string($webhookModel) && $webhookModel !== ''
                && is_string($processWebhookJob) && $processWebhookJob !== '') {
                return true;
            }
        }

        return false;
    }

    private function resolveDependencyPath(
        string $providerClass,
        string $relativePath,
        string $fallbackBasePath
    ): ?string {
        if (class_exists($providerClass)) {
            $providerFile = (new ReflectionClass($providerClass))->getFileName();

            if (is_string($providerFile) && $providerFile !== '') {
                $packageRoot = dirname($providerFile, 2);
                $resolved = $packageRoot . '/' . mb_ltrim($relativePath, '/');

                if (is_file($resolved) || is_dir($resolved)) {
                    return $resolved;
                }
            }
        }

        $fallback = base_path($fallbackBasePath);

        if (is_file($fallback) || is_dir($fallback)) {
            return $fallback;
        }

        return null;
    }

    private function resolveCommerceSupportAuditMigrationPath(): ?string
    {
        $path = dirname(__DIR__) . '/database/migrations/1970_01_01_000002_create_audits_table.php.stub';

        return is_file($path) ? $path : null;
    }

    private function auditTableExists(): bool
    {
        $connection = config('audit.drivers.database.connection') ?: config('database.default');
        $table = config('audit.drivers.database.table') ?: 'audits';

        return $this->tableExists((string) $table, is_string($connection) && $connection !== '' ? $connection : null);
    }

    private function tagTablesExist(): bool
    {
        return $this->tableExists('tags') && $this->tableExists('taggables');
    }

    private function tableExists(string $table, ?string $connection = null): bool
    {
        if ($connection === null || $connection === '') {
            return Schema::hasTable($table);
        }

        return Schema::connection($connection)->hasTable($table);
    }

    private function validateMorphKeyType(): void
    {
        $morphKeyType = (string) config('commerce-support.database.morph_key_type', 'uuid');

        if (! in_array($morphKeyType, ['int', 'uuid', 'ulid'], true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid morph key type: %s (allowed: int, uuid, ulid)', $morphKeyType)
            );
        }

        Schema::defaultMorphKeyType($morphKeyType);
    }

    /**
     * Ensure owner mode is not enabled with the no-op resolver.
     *
     * NullOwnerResolver always returns null for the current owner, which means:
     * - Multi-tenancy is effectively disabled
     * - All data is treated as "global" (no tenant isolation)
     * - Owner scopes will not filter data
     */
    private function ensureOwnerResolverIsConfiguredWhenOwnerModeEnabled(): void
    {
        $ownerModeEnabled = (bool) config('commerce-support.owner.enabled', false);

        if (! $ownerModeEnabled) {
            return;
        }

        if (! $this->app->bound(OwnerResolverInterface::class)) {
            throw new RuntimeException('OwnerResolverInterface must be bound when commerce-support owner mode is enabled.');
        }

        $resolver = $this->app->make(OwnerResolverInterface::class);

        if ($resolver instanceof NullOwnerResolver) {
            throw new RuntimeException(
                'NullOwnerResolver is configured while commerce-support owner mode is enabled. ' .
                'Configure commerce-support.owner.resolver with a resolver that implements ' . OwnerResolverInterface::class . '.'
            );
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [];
    }

    private function registerOwnerResolver(): void
    {
        if ($this->app->bound(OwnerResolverInterface::class)) {
            return;
        }

        /** @var class-string $resolverClass */
        $resolverClass = (string) config('commerce-support.owner.resolver', NullOwnerResolver::class);

        if ($resolverClass === '' || ! class_exists($resolverClass)) {
            throw new InvalidArgumentException(sprintf('Invalid owner resolver class: %s', $resolverClass));
        }

        if (! is_a($resolverClass, OwnerResolverInterface::class, true)) {
            throw new InvalidArgumentException(
                sprintf('%s must implement %s', $resolverClass, OwnerResolverInterface::class)
            );
        }

        $this->app->scoped(OwnerResolverInterface::class, function ($app): OwnerResolverInterface {
            /** @var class-string<OwnerResolverInterface> $resolverClass */
            $resolverClass = (string) config('commerce-support.owner.resolver', NullOwnerResolver::class);

            $resolver = $app->make($resolverClass);

            if (! $resolver instanceof OwnerResolverInterface) {
                throw new InvalidArgumentException(
                    sprintf('%s must implement %s', $resolverClass, OwnerResolverInterface::class)
                );
            }

            return $resolver;
        });
    }

    private function registerTargetingEngine(): void
    {
        $this->app->singleton(TargetingEngineInterface::class, function (): TargetingEngineInterface {
            return new TargetingEngine;
        });
    }

    private function registerPaymentSubjectResolver(): void
    {
        $this->app->singleton(PaymentSubjectResolver::class, function (): PaymentSubjectResolver {
            $resolver = new PaymentSubjectResolver;
            $resolver->register(new GuestPaymentSubjectDriver);

            return $resolver;
        });

        $this->app->alias(PaymentSubjectResolver::class, PaymentSubjectResolverInterface::class);
    }
}
