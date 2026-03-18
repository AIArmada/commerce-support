<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Concerns;

use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Models\Audit;

/**
 * Shared trait for compliance-grade auditing across commerce packages.
 *
 * This trait provides a standardized way to audit model changes using
 * owen-it/laravel-auditing with PII protection and comprehensive tracking.
 *
 * Key differences from activity logging:
 * - Separate old/new value columns for compliance queries
 * - Built-in IP/User-Agent tracking
 * - State restoration capability with transitionTo()
 * - PII redaction support
 *
 * @example
 * ```php
 * use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
 * use OwenIt\Auditing\Contracts\Auditable;
 *
 * class Order extends Model implements Auditable
 * {
 *     use HasCommerceAudit;
 *
 *     protected $auditInclude = ['status', 'total', 'customer_id'];
 * }
 * ```
 */
trait HasCommerceAudit // @phpstan-ignore trait.unused
{
    use Auditable;

    /**
     * Attributes to include in audit (if empty, all except $auditExclude).
     *
     * @var array<int, string>
     */
    protected $auditInclude = [];

    /**
     * Attributes to exclude from audit.
     *
     * @var array<int, string>
     */
    protected $auditExclude = [];

    /**
     * Maximum number of audit records to keep per model.
     */
    protected int $auditThreshold = 100;

    /**
     * Transform audit data before storage.
     *
     * This method allows redaction of PII or sensitive data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function transformAudit(array $data): array
    {
        // Redact sensitive fields
        $sensitiveFields = $this->getSensitiveFields();

        if (isset($data['old_values'])) {
            foreach ($sensitiveFields as $field) {
                if (isset($data['old_values'][$field])) {
                    $data['old_values'][$field] = '[REDACTED]';
                }
            }
        }

        if (isset($data['new_values'])) {
            foreach ($sensitiveFields as $field) {
                if (isset($data['new_values'][$field])) {
                    $data['new_values'][$field] = '[REDACTED]';
                }
            }
        }

        // Add commerce-specific metadata
        $existingTags = $data['tags'] ?? null;

        $tags = [];
        if (is_string($existingTags) && $existingTags !== '') {
            $tags = array_filter(array_map('trim', explode(',', $existingTags)));
        }

        $tags = array_merge($tags, $this->getAuditTags());

        $tags = array_values(array_unique(array_filter($tags)));
        $data['tags'] = $tags === [] ? null : implode(',', $tags);

        return $data;
    }

    /**
     * Get the attributes that should be audited.
     *
     * @return array<int, string>
     */
    public function getAuditInclude(): array
    {
        return $this->auditInclude;
    }

    /**
     * Get the attributes that should be excluded from audit.
     *
     * @return array<int, string>
     */
    public function getAuditExclude(): array
    {
        return array_merge($this->auditExclude, $this->getSensitiveFields());
    }

    /**
     * Get the maximum number of audit records to keep.
     */
    public function getAuditThreshold(): int
    {
        return $this->auditThreshold;
    }

    /**
     * Check if a specific attribute change should be audited.
     */
    public function isAuditableAttribute(string $attribute): bool
    {
        // If include list is specified, only audit those
        if (! empty($this->auditInclude)) {
            return in_array($attribute, $this->auditInclude, true);
        }

        // Otherwise, audit all except excluded
        return ! in_array($attribute, $this->getAuditExclude(), true);
    }

    /**
     * Restore the model to a previous audited state.
     *
     * @param  Audit  $audit
     * @param  bool  $old  Whether to restore to old values (true) or new values (false)
     */
    public function restoreToAuditState($audit, bool $old = true): bool
    {
        $values = $old ? $audit->old_values : $audit->new_values;

        if (empty($values)) {
            return false;
        }

        foreach ($values as $attribute => $value) {
            if ($this->isFillable($attribute)) {
                $this->setAttribute($attribute, $value);
            }
        }

        return $this->save();
    }

    /**
     * Get fields that should be redacted in audits.
     *
     * Override this to specify PII or sensitive data fields.
     *
     * @return array<int, string>
     */
    protected function getSensitiveFields(): array
    {
        return [
            'password',
            'password_hash',
            'remember_token',
            'api_key',
            'secret',
            'credit_card',
            'card_number',
            'cvv',
            'ssn',
            'tax_id',
        ];
    }

    /**
     * Get tags for categorizing this audit.
     *
     * @return array<int, string>
     */
    protected function getAuditTags(): array
    {
        return ['commerce'];
    }
}
