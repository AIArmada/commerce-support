<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

use AIArmada\CommerceSupport\Actions\ResolveOwnerJobContextAction;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Trait for queued jobs that require owner context.
 *
 * Automatically enters the owner context when the job runs, preventing
 * cross-tenant data access in a shared-database multitenancy model.
 *
 * Jobs using this trait should include either:
 * - a public owner-bearing model property,
 * - explicit public `ownerType` / `ownerId` (or snake_case equivalents) payload fields,
 * - or implement {@see OwnerScopedJob} for fully explicit owner context payloads.
 */
trait OwnerContextJob
{
    use SerializesModels;

    /**
     * Execute the job within owner context.
     *
     * This method wraps the actual job handler in `OwnerContext::withOwner(...)`.
     * Subclasses should implement `performJob()` instead of `handle()`.
     *
     * @internal Framework integration; do not override without understanding owner scoping.
     */
    final public function handle(): void
    {
        $ownerContext = $this->resolveOwnerContextFromJob();

        if ($ownerContext->isExplicitGlobal()) {
            OwnerContext::withOwner(null, function (): void {
                $this->performJob();
            });

            return;
        }

        $owner = $ownerContext->toOwnerModel();

        if (config('commerce-support.owner.enabled', false) && $owner === null) {
            throw new RuntimeException(
                sprintf(
                    '%s requires an owner context (ownerType + ownerId). Ensure the job payload includes owner data or an explicit global owner context.',
                    static::class,
                ),
            );
        }

        OwnerContext::withOwner($owner, function (): void {
            $this->performJob();
        });
    }

    /**
     * Perform the actual job logic within owner context.
     */
    abstract protected function performJob(): void;

    /**
     * Resolve owner context from the job payload.
     *
     * If the job implements {@see OwnerScopedJob}, that explicit context is used.
     * Otherwise, the trait inspects public properties for owner-bearing models,
     * `ownerType` / `ownerId` pairs, and explicit-global flags.
     */
    protected function resolveOwnerContextFromJob(): OwnerJobContext
    {
        return ResolveOwnerJobContextAction::run(job: $this);
    }
}
