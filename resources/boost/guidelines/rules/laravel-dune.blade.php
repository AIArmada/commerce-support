@if (\Composer\InstalledVersions::getRootPackage()['name'] !== 'aiarmada/commerce')
# Laravel Dune Path-Scoped Rules

These rules apply to application-owned code in apps that adopt Laravel Dune. Boost extracts each `@scoped` block into `.ai/rules/boost/` when scoped guidelines are enabled.

@scoped(['app/Platform/**'])

# Platform Core

## Strict One-Way Dependency
`app/Platform/**` MUST NEVER import or reference any class under `App\Features\*` or `App\Host\*`. Use generic contracts, PHP attributes, or manifest metadata (`feature.json`) instead.

## Zero Per-Request Disk Globs in Production
`FeatureCatalog::load()` MUST memoize in-memory per process and read from `bootstrap/cache/platform.php` when present. Any change to discovery or manifest loading must keep `php artisan platform:cache` and `php artisan optimize` compatible.

## Narrow Architecture Exceptions Only
Low-level framework mechanics (reflection, runtime strictness switches, service-provider wiring) belong here rather than in product features. Changes to `app/Platform/**` are architecture changes and require explicit review plus regression tests proving both passing and failing boundary cases.

@endscoped

@scoped(['app/Features/**'])

# Feature Modules

## Owned Capability Folder & Manifest
- Every directory under `app/Features/<PascalCase>/` must contain `feature.json` (schema v1) and `FeatureServiceProvider.php` extending `App\Platform\FeatureModule`.
- Declare every owned `table:<name>` and `store:<name>` in `feature.json` with the exact local `Actions\` or `Persistence\` FQCNs permitted to mutate it. Never register an `EntryPoints\` class as a write owner.
- Multiple named actions in the same feature (e.g. `PlaceOrder`, `CancelOrder`) may write the same owned table—do not create a god-class `OrderWriter`.

## Concrete Actions by Default
- Use concrete `final readonly class` Actions resolved automatically by Laravel's container. Do not create a redundant interface or repository for single-feature use cases.
- Actions own their `DB::transaction(...)` boundaries, explicit actor/tenant authorization checks (so jobs and CLI cannot bypass HTTP FormRequests), and idempotency on retried operations.

## Models Stay Private & Explicit
- Every concrete Eloquent model under `Models/` MUST declare an explicit literal `protected $table = '...';` matching a `table:*` owned by this feature.
- Never import another feature's `Models/`, `Actions/`, `Persistence/`, or `EntryPoints/`. Cross-feature reads/writes go strictly through the owning feature's `Api/` namespace.
- Keep mutation side effects out of Eloquent Observers and `booted()` hooks; observers/listeners may only delegate to a declared named Action.

## Thin EntryPoints & Isolated Routes
- `EntryPoints/{Http,Jobs,Console,Livewire,Filament}/` only parse/validate transport input, authorize the actor, call the Action/Query, and format the response.
- Feature route files (`Routes/web.php`, `Routes/api.php`, `Routes/console.php`) MUST declare their own middleware, URI prefixes, and route name groups explicitly.
- If overriding `FeatureServiceProvider::boot()`, always call `parent::boot()`.
- For repeatable domain plugs (widgets, tools, renderers, handlers), add an isolated class under `Extensions/` and register it with `ExtensionRegistry` instead of branching in a shared root file.

@endscoped

@scoped(['app/Features/*/Api/**', 'app/Shared/Contracts/**'])

# Public API & Shared Contracts

## Pure Immutable Boundary Shapes
- All classes in `app/Features/*/Api/**` and `app/Shared/Contracts/**` must be `interface`, `enum`, or `final readonly class` value objects / DTOs.
- NEVER expose Eloquent `Model`, `Builder`, `Collection`, `Request`, `Response`, or any `Illuminate\*` runtime type in public signatures or DTO properties. Use scalar/branded IDs, backed enums, `CarbonImmutable`, or immutable DTOs.

## Parse Once at the Boundary
- Validate and parse external/untrusted payloads once at the transport or Host boundary into typed DTOs. Inside features, trust typed domain values—do not duplicate defensive null/array checks.
- Public contracts may depend only on PHP built-ins, backed enums, `App\Shared\Contracts\*`, or another feature's `Api\*` types.

@endscoped

@scoped(['app/Host/**'])

# Host & External Boundaries

## Keep Privileged Runtime & SDKs in the Box
- All third-party HTTP/SDK integrations (Stripe, LLM providers, mail/SMS APIs, webhooks), filesystem gateways, and OS/process execution (`Process::run`) live in `app/Host/<Integration>/`.
- Each Host integration implements a pure interface defined in `app/Shared/Contracts/` and is bound in `App\Providers\AppServiceProvider` (or a dedicated Host provider).

## Direction & Isolation
- `app/Host/**` MUST NEVER import any class under `App\Features\*`.
- Parse raw third-party responses/webhooks at the Host boundary into immutable contract DTOs or domain exceptions—never leak vendor SDK objects or raw HTTP arrays into Features.
- Features call Host behavior exclusively through the typed interface in `App\Shared\Contracts\*`, making external boundaries trivially fakeable in tests without mocking internals.

@endscoped

@scoped(['app/**', 'tests/**', 'verify/**'])

# Verification, Hygiene & Anti-Slop Governance

## Comment-Sicko Policy (~99% Comment Ban)
Only five comment types may remain in committed code:
1. Legal or license headers.
2. Verified external vendor/framework quirks we cannot reshape (proven on a live path).
3. Purely stylistic formatter ignores (`// prettier-ignore`). Never keep correctness/type suppressions (`@phpstan-ignore*`, `phpcs:ignore`).
4. Public API contract docblocks (`Api/`, `Shared/Contracts/`, PHPStan generic templates).
5. Issue or RFC links explaining an unavoidable external constraint.

Delete all narrating comments, commented-out code, workaround justifications, and over-rotated PR feedback (`// X said never do this`). Convert `DO NOT REMOVE` comments into types, lints, or tests.

## Banned Shortcuts
- No `sleep()`, `usleep()`, `time_nanosleep()`, or `Sleep::sleep()` synchronization hacks in `app/`.
- No `env()` calls outside `config/`.
- No `Model::unguard()` or disabling mass-assignment protection in `app/`.
- No service-locator `app(...)` / `resolve(...)` inside Feature Actions when constructor DI works.

## Proof Before Done ("Prove It Works")
- Always run `composer arch:check` (AST boundaries + Larastan `OwnedWriteRule` + normal PHPStan) and affected Pest/PHPUnit tests.
- Test observable behavior (HTTP status/JSON, persisted DB state read back, authorization/tenant isolation, rollback on failure, idempotency on retry)—never pin tests to private helper calls.
- Verify route/config/platform caches (`php artisan route:cache`, `config:cache`, `platform:cache`) on structural changes and clear temporary caches after.
- Use Laravel Boost MCP (`last-error`, `browser-logs`, `database-query`) to confirm zero silent runtime errors.

## Rule → Enforcement Table (Updated via `laravel-dune-correct`)

| Mistake Class | Mechanical Enforcer | Layer |
| --- | --- | --- |
| Cross-feature private import | `php tools/architecture-check.php` (`feature-private`) | L2 AST |
| `app/Platform` / `app/Host` / `app/Shared` importing feature | `php tools/architecture-check.php` (`layer-direction`) | L2 AST |
| Unregistered DB / Cache / Storage / Redis write | PHPStan `OwnedWriteRule` (`architecture.writeOwner`) | L2 Type |
| Missing literal `$table` on feature model | `php tools/architecture-check.php` (`model-table`) | L2 AST |
| Mutable Laravel / Eloquent leak in `Api/` or `Contracts/` | `php tools/architecture-check.php` (`api-shape`, `api-leak`) | L2 AST |
| `sleep()` / `env()` / `Model::unguard()` / `@phpstan-ignore` | `php tools/architecture-check.php` (`banned-footgun`, `no-suppression`) | L2 AST |
| Narrative / workaround comment slop | `php tools/architecture-check.php` (`comment-hygiene`) + `laravel-dune-correct` | L2/L4 |
| Behavioral regressions & broken routes | `verify/feature-map/` + Pest feature & arch tests + Boost MCP | L3 Verify |

@endscoped
@endif
