<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

use AIArmada\CommerceSupport\Data\OwnerJobContext;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerJobContextMapper;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/** Restore the explicit owner context before executing queued work. */
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
        $ownerContext = $this->ownerContext();

        if ($ownerContext->isExplicitGlobal()) {
            OwnerContext::withOwner(null, function (): void {
                $this->performJob();
            });

            return;
        }

        $owner = OwnerJobContextMapper::resolve($ownerContext);

        if ($owner === null) {
            if (config('commerce-support.owner.enabled', false)) {
                throw new RuntimeException(
                    sprintf(
                        '%s requires an owner context (ownerType + ownerId). Ensure the job payload includes owner data or an explicit global owner context.',
                        static::class,
                    ),
                );
            }

            // Owner mode is disabled and no owner was declared: run directly
            // instead of entering explicit global so `isExplicitGlobal()` is
            // not falsely activated for the job body.
            $this->performJob();

            return;
        }

        OwnerContext::withOwner($owner, function (): void {
            $this->performJob();
        });
    }

    /**
     * Perform the actual job logic within owner context.
     */
    abstract protected function performJob(): void;

    abstract public function ownerContext(): OwnerJobContext;
}
