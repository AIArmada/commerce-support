---
title: Commerce Support Context
package: commerce-support
status: current
surface: foundation
family: foundation
keywords:
  - owner-scope
  - contracts
  - targeting
  - webhooks
  - health
  - money
  - primitives
---

# Commerce Support Context

## Snapshot
- Composer: `aiarmada/commerce-support`
- Role: Shared foundation: owner scoping, contracts, targeting engine, webhooks, health, money, reference data.
- Triggers: owner-scope, contracts, targeting, webhooks, health, money, primitives
- Search first: `src/Models, src/Actions, src/Support, src/Contracts, src/Targeting, config, docs`
- Related: `filament-authz`, `filament-commerce-support`
- Paired: `filament-commerce-support` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-commerce-support/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-commerce-support`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Cross-package primitives or owner-boundary questions — read first.
- Skip when: Domain logic — go to the owning package.
- Owner/security: Defines the owner boundary (HasOwner, OwnerScope, OwnerContext).

## Key surfaces
- Models: `AuthzScope`, `Currency`, `Language`, `NotificationPreference`, `Permission`, `Report`, `Role`, `SavedSearch`, `Tag`, `Timezone`
- Actions/Services: `Actions/DiscoverCommerceMigrationPublishTagsAction`, `Actions/DiscoverCommercePublishTagsAction`, `Actions/EnsureCustomGuidelinesSymlinkAction`, `Actions/ProcessWebhookCallAction`, `Actions/ResolveOwnedModelOrFailAction`, `Actions/ResolveOwnerJobContextAction`, `Actions/ResolveProjectRootAction`, `Actions/SeedCurrenciesAction`
- Config `commerce-support.php`: `database`, `morph_key_type`, `json_column_type`, `tables`, `saved_searches`, `reports`, `notification_preferences`, `languages`, `currencies`, `timezones`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `04-multi-tenancy.md`, `05-payment-contracts.md`, `06-targeting-engine.md`, `07-auditing-logging.md`, `08-webhooks.md`, `09-health-checks.md`, `10-traits-utilities.md`, `11-isolation-primitives.md`, `12-actions.md`, `13-reference-data.md`
