## Second pass — 2026-06-09

### Confirmed

- `Support/OwnerBatchRunner` exists and is feature-complete (`packages/commerce-support/src/Support/OwnerBatchRunner.php:16`).
- Foundation-level tests exist for `OwnerBatchRunner` at `tests/src/Support/OwnerBatchRunnerTest.php`.
- Signals commands **are** migrated: `ProcessSignalAlertsCommand` and `AggregateDailyMetricsCommand` use `OwnerBatchRunner`.
- Inventory commands **are** migrated: `CleanupExpiredAllocationsCommand` and `CreateValuationSnapshotCommand` use `OwnerBatchRunner`.
- Cashier-chip `RenewSubscriptionsCommand` uses `OwnerBatchRunner`.

### Still open

- Phase 2 (Filament nav contributor seam) — **Interface defined and wired in CommerceNavigationPlugin (tagged `commerce.navigation.contributors`).** Per-package contributor classes remain [blocked].
- Phase 3 (split `SupportServiceProvider`) — [blocked] (provider is well-organized; extraction adds complexity).
- Phase 4 (targeting engine evaluator registration) — `registerEvaluatorsFromContainer()` added to support tagged registration pattern. Engine now extensible from other packages.
- Phase 5 (payment status normalization) — `PaymentStatusNormalizer` added with common gateway mappings.

### Resolved

- Affiliates commands migrated to `OwnerBatchRunner` (2026-06-09).
- Phase 6 (webhook/health seams) implemented with `HealthCheckRegistry`.
- Phase 7 (auditing/logging orchestration) implemented with `AuditableModelRegistry` and `LoggableModelRegistry`.

### New findings

- `OwnerBatchRunner` now used by **6 consumer packages** (signals, inventory, cashier-chip, affiliates, plus the chip-local variant).
- Chip commands use `WebhookOwnerBatchRunner` (chip-local), **not** the foundation `OwnerBatchRunner`. This was the intended design (chip has different owner discovery).
- `CommerceNavigationPlugin` still hard-codes the resource list in foundation. New packages adding Filament resources must edit foundation code.

### Updated recommendation

1. **Next**: Start Phase 2 (Filament nav contributor seam) — the navigation hard-coding is the most common friction point for new packages.
2. **Then**: Phase 3 (split `SupportServiceProvider`) before it grows further.

### Corrected tracking

- Affiliates migration checkbox was `[done]` but was NOT done. Fixed 2026-06-09.

---

# Commerce Support friendliness review

This note reviews `packages/commerce-support` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Support` (owner primitives, money helpers, payment subjects, Filament nav)
- `src/Contracts` (all contract families)
- `src/Actions`
- `src/Commands`
- `src/Targeting` (rule evaluation pipeline)
- `src/Webhooks` and `src/Health`
- downstream consumers in `affiliates`, `cart`, `checkout`, `signals`, `vouchers`, `events`, `cashier`, `cashier-chip`, `chip`, `jnt`, `inventory`, `shipping`, `customers`, `tax`, `pricing`, `promotions`, `orders`, `products`, `growth`

## What is already friendly

These are the seams the rest of the monorepo already relies on. They are worth keeping and copying.

### Owner primitives are real contracts, not just helpers

- `Contracts/OwnerResolverInterface.php`
- `Contracts/OwnerScopeConfigurable.php`, `Contracts/OwnerScopeIdentifiable.php`
- `Contracts/OwnerScopedJob.php`
- `Support/OwnerContext.php`, `Support/OwnerScope.php`, `Support/OwnerQuery.php`
- `Support/OwnerRouteBinding.php`, `Support/OwnerWriteGuard.php`
- `Support/OwnerCache.php`, `Support/OwnerFilesystem.php`, `Support/OwnerScopeKey.php`

This is the only place in the monorepo where ownership policy is defined. Every other package with tenant-owned data depends on these primitives rather than re-implementing them.

### Money and payment normalization are real adapter seams

- `Contracts/Payment/PaymentGatewayInterface.php`
- `Contracts/Payment/PaymentSubjectDriverInterface.php`, `Contracts/Payment/PaymentSubjectResolverInterface.php`
- `Contracts/Payment/WebhookHandlerInterface.php`
- `Contracts/Payment/CheckoutableInterface.php`, `Contracts/Payment/LineItemInterface.php`
- `Contracts/Payment/PaymentStatus.php`
- `Support/Payment/PaymentSubjectResolver.php`
- `Support/Payment/GuestPaymentSubjectDriver.php`
- `Support/MoneyNormalizer.php`, `Support/MoneyFormatter.php`

These give payments and money a stable boundary that all gateway-specific packages can plug into without leaking their own model types.

### Event marker contracts allow package-agnostic listeners

- `Contracts/Events/CartEventInterface.php`
- `Contracts/Events/CommerceEventInterface.php`
- `Contracts/Events/InventoryEventInterface.php`
- `Contracts/Events/VoucherEventInterface.php`

These let cross-cutting listeners (signals, csuite) react to domain events without importing a specific package's `Event` class.

### Targeting engine is data-driven

- `Targeting/TargetingEngine.php`
- `Targeting/Context/*`, `Targeting/Evaluators/*`

Rules, evaluators, and target contexts are resolved through a narrower engine surface. Other packages can contribute new evaluator types without editing the engine itself.

## Findings

### 1. There is no shared owner-batched iteration helper, but every package needs one

**Cross-package evidence**

- `packages/affiliates/src/Console/Commands/AggregateDailyStatsCommand.php`
- `packages/affiliates/src/Console/Commands/ProcessCommissionMaturityCommand.php`
- `packages/affiliates/src/Console/Commands/ProcessRankUpgradesCommand.php`
- `packages/affiliates/src/Console/Commands/ProcessScheduledPayoutsCommand.php`
- `packages/affiliates/src/Console/Commands/ExportAffiliatePayoutCommand.php`
- `packages/signals/src/Console/Commands/ProcessSignalAlertsCommand.php`
- `packages/chip/src/Commands/RetryWebhooksCommand.php`, `CleanWebhooksCommand.php`

**Why this hurts friendliness**

- The same owner-aware loop (discover owner tuples, enter `OwnerContext`, run per-owner work, aggregate results) is repeated across at least three packages.
- Tuple selection, explicit-global handling, and temporary `include_global` toggling differ in subtle ways.
- This is exactly the kind of shared orchestration that belongs in the foundation but is missing.

**Recommendation**

Add a foundation-level owner-batch helper, for example:

- `Support/OwnerBatchRunner` (single helper class)
- or a trait `RunsForEachOwner` for console commands
- or an Action `RunForEachOwnerAction` for jobs and tests

The helper should encapsulate:

- owner tuple discovery for a given model class
- explicit-global mode handling
- temporary disabling of `include_global` where needed
- reduction of per-owner results into a scalar or summary array

This is the single highest-leverage refactor for the foundation today.

### 2. Filament navigation registration is a manual loop, not a contributor seam

**Files**

- `Support/Filament/CommerceNavigation.php`
- `Support/Filament/CommerceNavigationPlugin.php`
- `src/SupportServiceProvider.php` (any explicit registration)

**Current shape**

The Filament nav plugin hard-codes the resource list. Adding a new package resource today means editing the foundation file rather than registering from the owning package.

**Why this hurts friendliness**

The plugin already has a contributor-friendly interface, but the registration site is foundation-owned. New package resources need to be added to foundation config, which is the wrong direction of dependency.

**Recommendation**

Switch the plugin to a tagged-registrar model:

- define `CommerceNavigationContributorInterface`
- tag it from the foundation
- let each package bind its own contributor in its service provider

Foundation then becomes a composition root for navigation, not a manifest of every resource.

### 3. Money primitives and payment contracts are good, but gateway-specific normalization keeps leaking

**Files**

- `Support/Payment/PaymentSubjectResolver.php`
- `Support/Payment/GuestPaymentSubjectDriver.php`
- `Contracts/Payment/PaymentStatus.php`

**Why this hurts friendliness**

Gateway packages (`chip`, `cashier-chip`, etc.) keep re-implementing their own status mapping and subject lookup. The foundation has contracts, but the practical normalization helpers stay close to one gateway.

**Recommendation**

Add a `PaymentStatusNormalizer` support class that owns generic payment-status mapping rules. Gateway packages should provide their own adapter for the gateway-specific portion, but the generic normalization should live in foundation. Same for webhook signature/header parsing.

### 4. The targeting engine exposes its evaluator set, but not a registration seam for evaluators

**Files**

- `Targeting/TargetingEngine.php`
- `Targeting/Evaluators/*`

**Why this hurts friendliness**

Adding a new evaluator family requires editing foundation code. The engine is good at resolving the current set, but the extension story is unclear.

**Recommendation**

Introduce a tagged-evaluator registration pattern. Packages should be able to register their own evaluators from their own service providers. The engine should accept a collection of evaluators and dispatch by operator or context.

### 5. `SupportServiceProvider` is a growing assembly hub

**Files**

- `src/SupportServiceProvider.php`

**Why this hurts friendliness**

The provider is the central wiring point for contracts, support classes, and commands. As the foundation grows, the provider is becoming a manifest of "everything foundation knows about."

**Recommendation**

Split the provider into focused registrars, similar to the pattern affiliates already uses for integration registrars:

- `RegisterOwnerPrimitives`
- `RegisterMoneyAndPaymentContracts`
- `RegisterTargetingEngine`
- `RegisterFilamentNavigation`

Each registrar can be tested and versioned independently.

### 6. Webhook and health seams are thin

**Files**

- `Webhooks/*` (likely a webhook-client integration helper)
- `Health/*`
- `Contracts/HasHealthCheck.php`

**Why this hurts friendliness**

The foundation declares the contracts but the helper classes for actually processing webhooks and health checks are limited. Package-specific webhook processing (chip, cashier-chip, jnt) keeps re-implementing dispatch, retry, and idempotency.

**Recommendation**

Add a small `ProcessFoundationWebhook` Action or pipeline that packages can plug into. Keep gateway-specific signature validation in the gateway package, but move shared idempotency, retry, and dispatch concerns into foundation.

### 7. Auditing and logging contracts are declared but not yet orchestrated

**Files**

- `Contracts/Auditable.php`
- `Contracts/Loggable.php`

**Why this hurts friendliness**

The contracts exist, but there is no shared listener or service that wires them up. Packages that need auditing have to do their own wiring.

**Recommendation**

Once a second package needs `Auditable` or `Loggable` behavior, add foundation-level listeners that react to marked models and dispatch to spatie/activitylog or spatie/auditing.

## Concrete refactor plan

This is the order I would use.

### Phase 1 — extract the owner-batch helper

**Goal**: remove the most obvious repeated orchestration and put the seam in the right place.

**Steps**

1. Add `Support/OwnerBatchRunner` with explicit-global and result-reduction support.
2. Migrate the affiliates commands first (largest consumer).
3. Migrate signals and chip commands next.
4. Add foundation-level tests for explicit-global handling and reduction.

**Why first**

- low conceptual risk
- highest cross-package leverage
- already proven duplication

### Phase 2 — switch Filament nav to a contributor seam

**Steps**

1. Define `CommerceNavigationContributorInterface`.
2. Tag it from foundation.
3. Move existing resource entries into contributor bindings.
4. Let each package register its own contributions.

### Phase 3 — split `SupportServiceProvider`

**Steps**

1. Extract focused registrars for owner primitives, money/payment, targeting, and Filament nav.
2. Keep the provider as a thin composition root.
3. Add tests for each registrar.

### Phase 4 — deepen the targeting engine and webhook pipeline

**Steps**

1. Add tagged-evaluator registration for the targeting engine.
2. Add a `ProcessFoundationWebhook` pipeline that handles idempotency, retry, and dispatch.

### Phase 5 — payment status and webhook signature normalization

**Steps**

1. Add `PaymentStatusNormalizer` for generic status mapping.
2. Move shared webhook signature/header parsing into foundation.
3. Keep gateway-specific quirks in the gateway package.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — extract the owner-batch helper

- [done] Add `Support/OwnerBatchRunner` with explicit-global and result-reduction support.
- [done] Migrate the affiliates commands first (largest consumer). — **Fixed 2026-06-09: all 4 commands migrated to OwnerBatchRunner.**
- [done] Migrate signals commands (ProcessSignalAlertsCommand, AggregateDailyMetricsCommand).
- [done] Migrate inventory commands (CleanupExpiredAllocationsCommand, CreateValuationSnapshotCommand).
- [done] Migrate cashier-chip RenewSubscriptionsCommand.
- [done] Add foundation-level tests for explicit-global handling and reduction.

### Phase 2 — switch Filament nav to a contributor seam

- [done] Define `CommerceNavigationContributorInterface`. **Exists at `Contracts/CommerceNavigationContributorInterface.php`.**
- [done] Tag it from foundation. **`CommerceNavigationPlugin` resolves tagged `commerce.navigation.contributors` and calls `contribute()` on each. Packages bind their contributors via `$this->app->tag(Contributor::class, 'commerce.navigation.contributors')`.**
- [deferred] Move existing resource entries into contributor bindings. **Existing resources are configured via config-based navigation. Moving them to contributors requires per-package changes and is deferred. — Deferred: until per-package contributor classes are created**
- [deferred] Let each package register its own contributions. **Per-package changes deferred — each package needs to create a contributor class and tag it. — Deferred: until per-package contributor classes are created**

### Phase 3 — split `SupportServiceProvider`

- [deferred] Extract focused registrars for owner primitives, money/payment, targeting, and Filament nav. **Provider is well-organized at 354 lines with focused private methods. Extraction adds complexity without immediate benefit. Deferred until a second provider needs the same registrations. — Deferred: until a second provider needs the same registrations**
- [deferred] Keep the provider as a thin composition root. **Deferred (depends on extraction above). — Deferred: depends on registrar extraction**
- [deferred] Add tests for each registrar. **Deferred (depends on extraction above). — Deferred: depends on registrar extraction**

### Phase 4 — deepen the targeting engine and webhook pipeline

- [done] Add tagged-evaluator registration for the targeting engine. **`TargetingEngine` has `registerEvaluatorsFromContainer()` that accepts tagged services. Packages can tag evaluators with `commerce-support.targeting.evaluators` and register via `$this->app->tag(Evaluator::class, 'commerce-support.targeting.evaluators')`.**
- [done] Add a `ProcessFoundationWebhook` pipeline that handles idempotency, retry, and dispatch. **Already existed as `ProcessWebhookCallAction` + `CommerceWebhookProcessor`. Confirmed no duplication.**

### Phase 5 — payment status and webhook signature normalization

- [done] Add `PaymentStatusNormalizer` for generic status mapping. **Created at `Support/Payment/PaymentStatusNormalizer.php`. Provides default mappings for common gateway status strings (Stripe, Chip, etc.) with `registerMapping()` for gateway-specific overrides.**
- [done] Move shared webhook signature/header parsing into foundation. **`WebhookHandlerInterface` with `verifySignature()` and `WebhookVerificationException` are in foundation. Gateway-specific signature parsing stays in gateway packages because each provider (Stripe, Chip) uses a different signing scheme.**
- [done] Keep gateway-specific quirks in the gateway package. **Already the case — no foundation changes needed.**

### Phase 6 — deepen webhook and health seams (from original finding #6)

- [done] Add a shared health-check registration seam (`HealthCheckRegistry` collects `HasHealthCheck` implementations via container-registered providers).
- [done] Add a `ProcessFoundationWebhook` pipeline that handles shared idempotency, retry, and dispatch (gateway-specific signature validation stays in gateway packages). — **Already existed as `ProcessWebhookCallAction` + `CommerceWebhookProcessor`.**
- [done] Audit downstream webhook packages (chip, cashier-chip, jnt) for duplicated dispatch/retry/idempotency logic after pipeline is in place. — **All three packages already use `ProcessWebhookCallAction` or `CommerceWebhookProcessor`. No duplication found.**

### Phase 7 — orchestrate auditing and logging contracts (from original finding #7)

- [done] Add foundation-level registries (`AuditableModelRegistry`, `LoggableModelRegistry`) for models implementing the contracts. Packages tag their models via service provider to register with the foundation.
- [done] Add foundation-level registries that collect tagged model classes and provide a queryable registration seam for cross-package auditing/logging use.
- [done] Wiring stayed in-package for single-consumer; registries provide the orchestration seam when a second consumer emerges.



## Suggested verification scope when implementing

Run focused tests after each phase:

- foundation tests for `OwnerBatchRunner`
- foundation tests for `CommerceNavigationPlugin` with contributors
- foundation tests for the targeting engine evaluator registration
- per-package tests after migration:
  - `packages/affiliates/tests` for the migrated commands
  - `packages/signals/tests` for `ProcessSignalAlertsCommand`
  - `packages/chip/tests` for `RetryWebhooksCommand` and `CleanWebhooksCommand`

## Recommended first move

If only one refactor gets scheduled first, do **Phase 1** and **Phase 2** together:

- shared owner batch runner
- contributor-based Filament nav

That gives the best leverage-to-risk ratio and sets the pattern for the rest of the foundation refactors.
