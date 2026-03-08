<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingEngineInterface;
use AIArmada\CommerceSupport\Targeting\TargetingEngine;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

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
        $this->registerTargetingEngine();
    }

    public function bootingPackage(): void
    {
        $this->validateMorphKeyType();
        $this->warnAboutNullOwnerResolver();

        // Commerce Support - Octane Compatibility
        // Ensure static state is cleared between requests
        if (class_exists(\Laravel\Octane\Events\RequestReceived::class)) {
            $this->app['events']->listen(\Laravel\Octane\Events\RequestReceived::class, function (): void {
                \AIArmada\CommerceSupport\Support\OwnerContext::clearOverride();
            });
        }
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
     * Warn developers when using NullOwnerResolver with owner mode enabled.
     *
     * NullOwnerResolver always returns null for the current owner, which means:
     * - Multi-tenancy is effectively disabled
     * - All data is treated as "global" (no tenant isolation)
     * - Owner scopes will not filter data
     *
     * This is fine for single-tenant applications, but dangerous if you expect
     * multi-tenant behavior. The warning helps catch configuration mistakes.
     */
    private function warnAboutNullOwnerResolver(): void
    {
        $resolverClass = (string) config('commerce-support.owner.resolver', NullOwnerResolver::class);
        $ownerModeEnabled = (bool) config('commerce-support.owner.enabled', false);

        if ($resolverClass !== NullOwnerResolver::class && ! is_a($resolverClass, NullOwnerResolver::class, true)) {
            return;
        }

        if (! $ownerModeEnabled) {
            return;
        }

        if ($this->app->runningUnitTests()) {
            return;
        }

        $message = sprintf(
            '[commerce-support] NullOwnerResolver is configured but owner mode is enabled (commerce-support.owner.enabled=true). ' .
            'This means multi-tenancy is NOT enforced. To enable multi-tenancy, configure a real owner resolver: ' .
            'config/commerce-support.php -> owner.resolver = YourOwnerResolver::class'
        );

        if ($this->app->isProduction()) {
            Log::warning($message);
        } else {
            Log::debug($message);
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
}
