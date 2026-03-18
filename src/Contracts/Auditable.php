<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts;

use OwenIt\Auditing\Models\Audit;

/**
 * Interface for models that support compliance auditing.
 *
 * This extends the owen-it/laravel-auditing contract with
 * commerce-specific requirements.
 */
interface Auditable extends \OwenIt\Auditing\Contracts\Auditable
{
    /**
     * Get fields that should be redacted in audits.
     *
     * @return array<int, string>
     */
    public function getSensitiveFields(): array;

    /**
     * Get tags for categorizing this audit.
     *
     * @return array<int, string>
     */
    public function getAuditTags(): array;

    /**
     * Restore the model to a previous audited state.
     *
     * @param  Audit  $audit
     * @param  bool  $old  Whether to restore to old values (true) or new values (false)
     */
    public function restoreToAuditState($audit, bool $old = true): bool;
}
