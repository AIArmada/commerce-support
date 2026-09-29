<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Filament\Concerns;

/**
 * Re-verifies a relation manager's `ownerRecord` against the current owner
 * scope on every request, aborting with 403 on mismatch.
 *
 * This is the abort-style companion to `VerifiesRecordOwnerContext`: it
 * reuses the same stamp-and-reverify logic (mount-time stamp, per-request
 * comparison, fresh scoped visibility check, fixed-owner pins, locked
 * snapshot prop) but fails with `abort(403)` instead of clearing, because
 * a relation manager cannot render empty — `ownerRecord` is non-nullable
 * and every child query derives from it.
 *
 * Hook wiring is automatic: Livewire invokes `mount{Trait}` /
 * `hydrate{Trait}` for every used trait (including nested ones), so
 * consuming relation managers only `use` this trait. The mount hook
 * aborts the whole page when the owner record is already cross-owner;
 * the hydrate hook runs before action handlers on subsequent requests,
 * so a mid-session owner change, reassignment, or deletion blocks both
 * stale child tables and untrusted writes.
 *
 * The guard skips silently (nothing to protect) when the owner record is
 * not owner-scoped or owner scoping is disabled for the model.
 *
 * Composed failure mode: on updates, Filament's own
 * `hydrateCanAuthorizeAccess` hook runs first and touches the owner record
 * for managers whose `canViewForRecord()` resolves it — a row deleted
 * after mount then surfaces as 404 (`ModelNotFoundException`). Managers
 * whose authorization never touches the record (e.g. overrides checking
 * only class availability) reach this guard instead, which converts the
 * missing row to its 403. An existing-but-cross-owner row always aborts
 * 403 here. All paths are fail-closed.
 */
trait VerifiesRelationManagerOwnerContext
{
    use VerifiesRecordOwnerContext;

    protected function ownerGuardedPropName(): string
    {
        return 'ownerRecord';
    }

    protected function failOwnerGuard(): void
    {
        abort(403);
    }
}
