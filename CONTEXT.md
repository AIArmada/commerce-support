---
title: Commerce Support Context
package: commerce-support
status: current
surface: foundation
family: foundation
---

# Commerce Support Context

## Snapshot
- Composer: `aiarmada/commerce-support`
- Role: Owner scoping, shared contracts, targeting, webhooks, health checks, and common commerce primitives.
- Search first: `src/Models`, `src/Actions`, `src/Services`, `src/Support`, `config`, `docs`
- Related: `filament-authz`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-authz/CONTEXT.md` when panel auth or impersonation is involved
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns shared primitives, contracts, and cross-package rules.
- Changes can ripple across many packages; audit dependents before shipping.
- Update `docs/*.md` in the same pass when public behavior or config changes.
