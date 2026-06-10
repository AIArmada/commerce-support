---
title: Lifecycle Audit & Remediation Plan
package: commerce-support
status: audit-complete
audit-date: 2026-06-10
---

# Lifecycle Audit & Remediation Plan

## Executive Summary

The `commerce-support` package provides shared primitives for payment status management and webhook processing used by multiple consumer packages (chip, jnt, etc.). An audit of lifecycle handling revealed four gaps:

1. **`webhook_calls` table has no `status` column** — implicit lifecycle via `processed_at IS NULL` cannot distinguish permanent failure from pending retry.
2. **`webhook_calls` has no `failed_at` timestamp** — no record of when a webhook call transitioned to a terminal failure state.
3. **`HasPaymentStatus` trait sets no transition timestamps** — convenience methods (`markAsPaid`, `markAsFailed`, etc.) update the status enum but never record `paid_at`, `failed_at`, `cancelled_at`, or `refunded_at`.
4. **No mapping from `PaymentStatus` values to timestamp column names** — packages that use `HasPaymentStatus` have no canonical way to know which timestamp column corresponds to which status transition.

These gaps make it impossible to:
- Query "webhooks that permanently failed in the last hour" separately from "webhooks awaiting retry".
- Report time-to-payment, time-to-refund, or time-to-cancellation without inspecting separate activity logs.
- Implement consistent retry/backoff policies across all commerce packages.

## Full Inventory

### Webhook Call Model

| Surface | Location | Current State | Gap |
|---|---|---|---|
| Base migration | `database/migrations/1970_01_01_000004_create_webhook_calls_table.php.stub` | `id`, `name`, `url`, `headers` (json), `payload` (json), `exception` (text), `processed_at` (timestampTz), `created_at`, `updated_at` | Missing: `status`, `failed_at`, `retry_count`, `last_retry_at` |
| Processing action | `src/Actions/ProcessWebhookCallAction.php` | Locks row, checks `processed_at !== null` as sole completion signal | Cannot distinguish success/failure/retry |
| Processor base | `src/Webhooks/CommerceWebhookProcessor.php` | Extends `ProcessWebhookJob`, delegates to `ProcessWebhookCallAction` | No status lifecycle hooks |
| Consumer (chip) | `packages/chip/src/Models/Webhook.php` | Added `status`, `retry_count`, `last_retry_at`, `last_error`, `processing_time_ms` as package-local columns | Inconsistent; each consumer must reinvent |
| Consumer (chip) mark methods | `chip/src/Models/Webhook.php:137-173` | `markProcessed()`, `markFailed()`, `markForRetry()` are package-specific | Should live in `commerce-support` base |
| Consumer (jnt) | `packages/jnt/database/migrations/...add_jnt_webhook_columns...` | Added `processing_status`, `processing_error` | Another independent reinvention |

### Payment Status Lifecycle

| Surface | Location | Current State | Gap |
|---|---|---|---|
| Status enum | `src/Contracts/Payment/PaymentStatus.php` | 11 statuses with `canTransitionTo()`, `getAllowedTransitions()`, `transitionTo()` | No timestamp mapping |
| Trait | `src/Traits/HasPaymentStatus.php` | `transitionPaymentStatus()`, `markAsPaid()`, `markAsFailed()`, `markAsCancelled()`, `markAsRefunded()`, `isPaid()`, etc. | No timestamp columns set on transition |
| Trait boot | `HasPaymentStatus::bootHasPaymentStatus()` | Validates transitions on `saving` event | Only validates, no side effects |
| Base migration | N/A (trait is attached to consumer models) | Consumer packages define their own `payment_status` column | No convention for timestamp columns |

### PaymentStatus Enum — Business-Critical Transition Timestamps

Only terminal/business-critical states get timestamp columns. Transient states (PENDING, PROCESSING, REQUIRES_ACTION, AUTHORIZED, PARTIALLY_REFUNDED, EXPIRED, DISPUTED) do not record transitions.

| Status | Terminal? | Timestamp Column |
|---|---|---|
| `CREATED` | No | (initial, no timestamp) |
| `PENDING` | No | — |
| `PROCESSING` | No | — |
| `REQUIRES_ACTION` | No | — |
| `AUTHORIZED` | No | — |
| `PAID` | Yes | `paid_at` |
| `PARTIALLY_REFUNDED` | No | — |
| `REFUNDED` | Yes | `refunded_at` |
| `FAILED` | Yes | `failed_at` |
| `CANCELLED` | Yes | `cancelled_at` |
| `EXPIRED` | No | — |
| `DISPUTED` | No | — |

## Problems Summary

### P1: Implicit webhook lifecycle (no status column)

**Current behavior:** `ProcessWebhookCallAction` locks the row, checks `processed_at !== null`, and skips if already processed. If processing throws, the exception propagates to the queue worker, which retries. The row remains with `processed_at = NULL`, indistinguishable from a row that has never been processed.

**Why it matters:** Cannot answer "how many webhooks permanently failed?" separately from "how many are in the retry queue?". Monitoring/alerting requires parsing queue dead-letter stores or exception logs.

**Consumer workaround (chip):** Added a `status` column with values `pending`/`processed`/`failed`. The `markProcessed()` and `markFailed()` methods set this explicitly. But this is not available to other packages.

### P2: No failed_at timestamp on webhook_calls

**Current behavior:** When a webhook call fails permanently, the only record is the `exception` text column and (in chip's case) `last_error`. There is no dedicated timestamp for when the failure occurred.

**Why it matters:** Cannot sort/query by failure time. Cannot implement time-based retry policies ("retry if failed less than 24h ago"). Cannot generate "failed webhooks in time range" reports.

### P3: HasPaymentStatus sets no transition timestamps

**Current behavior:** `markAsPaid()` calls `transitionPaymentStatus(PaymentStatus::PAID)` which sets the `payment_status` attribute and saves. No `paid_at = now()` is set.

**Why it matters:** Every consumer package must implement its own timestamp logic in observers or action classes. This leads to:
- Inconsistent column naming (`paid_at` vs `payment_paid_at` vs `completed_at`).
- Missing timestamps when convenience methods are used directly.
- Duplicate code across orders, payments, subscriptions, and invoices packages.

### P4: No canonical PaymentStatus → timestamp column mapping

**Current behavior:** There is no contract, config, or constant that says "when transitioning to `PAID`, set the `paid_at` column".

**Why it matters:** Any automation (reporting, analytics, audit logs, webhook handlers) must hard-code the mapping or introspect the model. This is fragile and inconsistent across packages.

## Recommended Structure

### 1. Add webhook_calls lifecycle columns (commerce-support base migration)

Create a new migration in `commerce-support` that adds to the `webhook_calls` table (safe/idempotent with column-exists checks):

```sql
ALTER TABLE webhook_calls
  ADD COLUMN IF NOT EXISTS status VARCHAR(50) NOT NULL DEFAULT 'pending',
  ADD COLUMN IF NOT EXISTS failed_at TIMESTAMP WITH TIME ZONE NULL,
  ADD COLUMN IF NOT EXISTS retry_count INTEGER NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS last_retry_at TIMESTAMP WITH TIME ZONE NULL;
```

### 2. Add webhook lifecycle methods to commerce-support

Create a `HasWebhookLifecycle` trait (or add to `CommerceWebhookProcessor`):

```php
trait HasWebhookLifecycle
{
    public function markProcessed(float $processingTimeMs = 0): static;
    public function markFailed(\Throwable $exception): static;
    public function markForRetry(string $reason): static;
    public function isPending(): bool;
    public function isProcessed(): bool;
    public function isFailed(): bool;
}
```

### 3. Add timestamp auto-set to HasPaymentStatus

Extend `transitionPaymentStatus()` to accept an optional timestamp map. Provide a default mapping from `PaymentStatus` to column names. Only business-critical terminal states get timestamps:

```php
public function transitionPaymentStatus(
    PaymentStatus $newStatus,
    bool $save = true,
    ?array $timestamps = null
): static {
    // ... existing validation ...

    $this->setAttribute($attribute, $newStatus);

    // Auto-set transition timestamps for terminal states only
    $timestamps ??= $this->getPaymentStatusTimestamps();
    $column = $timestamps[$newStatus->value] ?? null;
    if ($column !== null) {
        $this->setAttribute($column, now());
    }

    if ($save) { $this->save(); }
    return $this;
}
```

### 4. Add PaymentStatusTimestampMap contract/interface

```php
interface HasPaymentStatusTimestamps
{
    /**
     * @return array<string, string>  status value => timestamp column name
     */
    public function getPaymentStatusTimestamps(): array;
}
```

Provide a default implementation in `HasPaymentStatus` — capped at four business-critical transitions:

```php
public function getPaymentStatusTimestamps(): array
{
    return [
        PaymentStatus::PAID->value       => 'paid_at',
        PaymentStatus::REFUNDED->value   => 'refunded_at',
        PaymentStatus::FAILED->value     => 'failed_at',
        PaymentStatus::CANCELLED->value  => 'cancelled_at',
    ];
}
```

Models can override column names by implementing `getPaymentStatusTimestamps()`.

## Refactoring Plan

### Phase 1: commerce-support base (foundation)

- [x] **P1.1** Create new migration stub: `1970_01_01_000005_add_webhook_lifecycle_columns.php.stub`
  - Add `status`, `failed_at`, `retry_count`, `last_retry_at` columns (idempotent)
- [x] **P1.2** Create `HasWebhookLifecycle` trait in `src/Traits/`
  - `markProcessed(float $processingTimeMs = 0): static`
  - `markFailed(Throwable $exception): static`
  - `markForRetry(string $reason): static`
  - `isPending(): bool`, `isProcessed(): bool`, `isFailed(): bool`
- [x] **P1.3** Update `ProcessWebhookCallAction` to use lifecycle methods
  - On success: call `markProcessed()` (or set `status = 'processed'` directly on the locked row)
  - On failure: catch exception, call `markFailed()`, re-throw
  - Distinguish duplicate skip (mark processed) from actual processing
- [x] **P1.4** Create `HasPaymentStatusTimestamps` interface in `src/Contracts/Payment/`
- [x] **P1.5** Update `HasPaymentStatus` trait
  - Implement `getPaymentStatusTimestamps()` with default mapping (4 business-critical transitions only)
  - Auto-set transition timestamps in `transitionPaymentStatus()`
  - Keep backward compatibility (timestamps only set if column exists on model)
- [x] **P1.6** Update `PaymentGatewayException::invalidStatusTransition()` to be type-safe (already using `PaymentStatus`, no changes needed)

### Phase 2: Consumer package alignment

- [x] **P2.1** Chip: replace `Webhook::markProcessed/markFailed/markForRetry` with `HasWebhookLifecycle` trait methods
- [x] **P2.2** Chip: add `paid_at`, `failed_at`, `cancelled_at`, `refunded_at` columns to relevant models (Purchase, etc.) if missing
- [x] **P2.3** JNT: replace `processing_status`/`processing_error` with `HasWebhookLifecycle` columns
- [x] **P2.4** Any other package with webhook_calls columns: audit and align

### Phase 3: Verification

- [x] **P3.1** Write contract tests for `HasPaymentStatusTimestamps`
- [x] **P3.2** Write contract tests for `HasWebhookLifecycle`
- [x] **P3.3** Update `PaymentGatewayContractTests` to verify timestamp setting
- [x] **P3.4** Run existing test suites to confirm no regressions

## Migration Strategy

### Backward compatibility

1. **New columns are additive only** — existing `processed_at IS NULL` checks continue to work.
2. **Default `status = 'pending'`** — existing rows get the default, compatible with current behavior.
3. **Timestamp auto-set is opt-in safe** — `HasPaymentStatus` checks `$model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), $column)` before setting, so models without timestamp columns are unaffected.
4. **Consumer packages can adopt incrementally** — no breaking changes to chip/jnt; their existing columns coexist with the new base columns (package migrations can drop their custom columns later).

### Rollout order

1. Merge `commerce-support` P1 changes (phase 1).
2. Publish migration via `php artisan vendor:publish --tag=commerce-support-migrations`.
3. Run migration — existing data unchanged (new columns get defaults).
4. Publish trait/interface updates.
5. Consumer packages update at their own pace (phase 2).

### Rollback

- Migration has `down()` that drops the added columns.
- Trait changes are additive; reverting to prior version restores old behavior.

## Verification Commands

```bash
# 1. Verify webhook_calls table has new columns
php artisan tinker --execute '
  $cols = Schema::getColumnListing("webhook_calls");
  $required = ["status", "failed_at", "retry_count", "last_retry_at"];
  $missing = array_diff($required, $cols);
  echo empty($missing) ? "All columns present" : "Missing: " . implode(", ", $missing);
'

# 2. Verify HasWebhookLifecycle trait contract
./vendor/bin/phpstan analyse packages/commerce-support/src/Traits/HasWebhookLifecycle.php --level=6

# 3. Verify HasPaymentStatus sets timestamps (unit test)
./vendor/bin/pest --parallel tests/Unit/PaymentStatusLifecycleTest.php

# 4. Verify no regressions in payment gateway contract tests
./vendor/bin/pest --parallel packages/commerce-support/tests/

# 5. Verify consumer packages still pass
./vendor/bin/pest --parallel packages/chip/tests/
./vendor/bin/pest --parallel packages/jnt/tests/

# 6. Grep for unscoped webhook status checks (should all use trait/contract)
rg -n -- "processed_at IS NULL\|processed_at is null" packages/*/src
rg -n -- "processing_status\|processing_error" packages/*/src

# 7. Verify all HasPaymentStatus consumers have timestamp columns configured
rg -n -- "use HasPaymentStatus" packages/*/src
# For each result, check the model has getPaymentStatusTimestamps() or timestamp columns
```
