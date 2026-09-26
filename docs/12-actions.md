---
title: Laravel Actions
---

# Laravel Actions

Commerce Support provides a suite of reusable [Laravel Actions](https://github.com/lorisleiva/laravel-actions) that extract common orchestration patterns. These actions follow the monorepo convention of using the `AsAction` trait with a static `::run()` entry point for consistent, testable, and injectable orchestration.

## When to Use Actions

Actions are ideal for:
- **Reusable orchestration** across multiple entry points (commands, API controllers, event handlers, jobs)
- **Testability** — actions are simple, focused classes with single entry points
- **Dependency injection** — container automatically resolves action dependencies
- **Consistency** — standardize complex workflows across the package

## Reference

| Action | Purpose | Entry Point |
|--------|---------|------------|
| [ResolveOwnedModelOrFailAction](#resolveownedmodelorfailaction) | Owner-scoped model lookup with authorization | `::run(modelClass, id, owner, includeGlobal, message)` |
| [ResolveOwnerJobContextAction](#resolveownerjobcontextaction) | Extract owner context from queued jobs | `::run(job)` |
| [ProcessWebhookCallAction](#processwebhookcallaction) | Webhook transaction, deduplication, event extraction | `::run(webhookCall, extractEventType, extractEventId, isDuplicateProcessedEvent, processEvent, extractOwner)` |
| [UpsertEnvVariablesAction](#upsertenvvariablesaction) | Parse and upsert .env file key-value pairs | `::run(updates, force, warn, info)` |
| [DiscoverCommercePublishTagsAction](#discovercommercepublishtagsaction) | Discover publish tags for configs + migrations | `::run(includeConfig)` |
| [DiscoverCommerceMigrationPublishTagsAction](#discovercommercemigrationpublishtagsaction) | Discover publish tags for migrations only | `::run()` |
| [ResolveProjectRootAction](#resolveprojectrootaction) | Detect project root in monorepo/testbench context | `::run()` |
| [EnsureCustomGuidelinesSymlinkAction](#ensurecustomguidelinessymlinkaction) | Create .ai/guidelines symlink for testbench | `::run(projectRoot, warn)` |
| [OwnerBatchRunner](#ownerbatchrunner) | Iterate over owners and run a callback for each | `new OwnerBatchRunner($modelClass, $ownerConfig)` then `->run($callback)` |

---

## ResolveOwnedModelOrFailAction

**Purpose:** Resolve a model instance within an owner scope with authorization checks.

**Use case:** Filament actions, API controllers, tests that need owner-scoped model lookup.

```php
use AIArmada\CommerceSupport\Actions\ResolveOwnedModelOrFailAction;
use AIArmada\Products\Models\Product;

$product = ResolveOwnedModelOrFailAction::run(
    modelClass: Product::class,
    id: $productId,
    owner: auth()->user(),
    includeGlobal: false // Set true to also include global (owner = null) records
);
```

**Throws:** `AuthorizationException` when the record is not visible in the resolved owner scope, and `InvalidArgumentException` when the model does not implement owner scoping, when owner scoping is explicitly disabled for it, or when `$owner` is a string.

**Integration:** Already used by `OwnerWriteGuard::findOrFailForOwner()`.

**Recommendation:** For package/app write handlers, prefer `OwnerWriteGuard::findOrFailForOwner()` directly. Use `ResolveOwnedModelOrFailAction` when you need custom orchestration or dependency-injected composition.

---

## ResolveOwnerJobContextAction

**Purpose:** Extract owner context from queued job payloads for [OwnerScopedJob](./04-multi-tenancy.md) contract compliance.

**Use case:** Job processing, ensuring jobs restore the correct owner context before execution.

```php
use AIArmada\CommerceSupport\Actions\ResolveOwnerJobContextAction;

// Automatically detects job properties via reflection:
// - Implements OwnerScopedJob contract path
// - Parses ownerType/ownerId payload fields
// - Validates consistency
$context = ResolveOwnerJobContextAction::run($job);

// Result:
// OwnerJobContext {
//   owner: Owner|null,
//   includeGlobal: bool,
// }
```

**Integration:** Used by `OwnerContextJob` trait to resolve owner before job handling.

---

## ProcessWebhookCallAction

**Purpose:** Orchestrate webhook processing with transaction, deduplication, and event extraction.

**Use case:** Custom webhook processors that need to safely handle webhook calls.

```php
use AIArmada\CommerceSupport\Actions\ProcessWebhookCallAction;

ProcessWebhookCallAction::run(
    webhookCall: $webhookCall,
    extractEventType: fn (array $payload): string => $payload['event'],
    extractEventId: fn (array $payload): ?string => $payload['id'] ?? null,
    isDuplicateProcessedEvent: function (WebhookCall $current, array $payload, string $eventType): bool {
        return Event::where('event_type', $eventType)
            ->where('external_id', $payload['id'] ?? null)
            ->exists();
    },
    processEvent: function (string $eventType, array $eventPayload): void {
        // Process the webhook event
        Event::dispatch(new WebhookEventReceived($eventType, $eventPayload));
    },
    extractOwner: fn (array $payload): array => [$payload['__owner_type'] ?? null, $payload['__owner_id'] ?? null],
);
```

**Guarantees:**
- Returns `void`; it never returns a value
- Event type/ID extraction and the `processing` claim run inside a short `lockForUpdate()` transaction
- The `processEvent` callback runs **after** that transaction has been released, so it must not depend on a held row lock
- A `UNIQUE(name, event_id, event_type, owner_hash)` violation marks the loser processed without running side effects
- A `processing` claim older than 30 minutes is treated as stale and reclaimable
- Automatic failure tracking (`status = failed`, `failed_at`, truncated `exception`)

**Integration:** Used by `CommerceWebhookProcessor::handle()`.

---

## UpsertEnvVariablesAction

**Purpose:** Parse and safely upsert key-value pairs in .env files.

**Use case:** Installation commands, configuration setup scripts.

```php
use AIArmada\CommerceSupport\Actions\UpsertEnvVariablesAction;

UpsertEnvVariablesAction::run(
    updates: [
        'COMMERCE_STRIPE_SECRET' => 'sk_live_...',
        'COMMERCE_DEBUG' => 'false',
    ],
    force: $this->option('force'), // Overwrite existing values
    warn: fn (string $message) => $this->components->warn($message),
    info: fn (string $message) => $this->components->info($message),
);
```

`force`, `warn`, and `info` are required. The action always writes `base_path('.env')`.

**Features:**
- Line-by-line parsing (preserves formatting)
- Automatic detection of existing keys
- Safe value escaping (no shell injection)
- Atomic file write
- Preserves comments and blank lines

**Integration:** Used by `SetupCommand::updateEnvFile()`.

---

## DiscoverCommercePublishTagsAction

**Purpose:** Discover publish tags for Commerce migrations + configs.

**Use case:** Installation and update commands that need to identify what can be published.

```php
use AIArmada\CommerceSupport\Actions\DiscoverCommercePublishTagsAction;

$tags = DiscoverCommercePublishTagsAction::run(includeConfig: true);

// Result: array<class-string, array<int, string>>
// [
//   'AIArmada\\Cart\\CartServiceProvider' => ['cart-config', 'cart-migrations'],
//   'AIArmada\\Products\\ProductsServiceProvider' => ['products-config', 'products-migrations'],
//   ...
// ]
```

Tags come from each package's spatie `shortName`, so they are
`{package}-migrations` and (with `includeConfig`) `{package}-config` — not
`commerce-{package}-*`.

**Integration:** Used by `InstallCommand`.

---

## DiscoverCommerceMigrationPublishTagsAction

**Purpose:** Discover publish tags for migrations only (subset of above).

**Use case:** Commands that only need to publish migrations, not configs.

```php
use AIArmada\CommerceSupport\Actions\DiscoverCommerceMigrationPublishTagsAction;

$migrationTags = DiscoverCommerceMigrationPublishTagsAction::run();

// Result: array<class-string, array<int, string>>
// [
//   'AIArmada\\Cart\\CartServiceProvider' => ['cart-migrations'],
//   'AIArmada\\Products\\ProductsServiceProvider' => ['products-migrations'],
//   ...
// ]
```

**Integration:** Used by `PublishMigrationsCommand`.

---

## ResolveProjectRootAction

**Purpose:** Detect project root path in monorepo and testbench environments.

**Use case:** Boost commands, local development setup, test fixture generation.

```php
use AIArmada\CommerceSupport\Actions\ResolveProjectRootAction;

$root = ResolveProjectRootAction::run();

// Resolution order:
// 1. Current working directory with composer.json
// 2. Orchestra\Testbench::package_path()
// 3. Laravel base_path()
```

**Returns:** Absolute path to project root.

**Integration:** Used by `BoostInstallCommand` and `BoostUpdateCommand`.

---

## EnsureCustomGuidelinesSymlinkAction

**Purpose:** Create symlink from testbench skeleton to custom .ai/guidelines in project root.

**Use case:** Boost installation, ensuring Copilot guidelines are accessible in testbench context.

```php
use AIArmada\CommerceSupport\Actions\EnsureCustomGuidelinesSymlinkAction;

EnsureCustomGuidelinesSymlinkAction::run(
    projectRoot: base_path(),
    warn: fn($message) => $this->warn($message)
);
```

**Safety features:**
- Skips if source missing (custom guidelines optional)
- Skips if already linked (idempotent)
- Creates parent directories automatically
- Removes stale symlinks safely
- Never deletes real directories

**Integration:** Used by `BoostInstallCommand` and `BoostUpdateCommand`.

---

## OwnerBatchRunner

**Purpose:** Iterate over all owners of a given model class and run a callback for each, in a scoped `OwnerContext`.

**Use case:** Console commands, batch jobs, scheduled tasks that need to operate per-owner.

```php
use AIArmada\CommerceSupport\Support\OwnerBatchRunner;

// $ownerConfig is a map of *config key paths*, not values:
// ['enabled' => 'products.owner.enabled', 'include_global' => 'products.owner.include_global']
$runner = new OwnerBatchRunner(
    modelClass: Product::class,
    ownerConfig: [
        'enabled' => 'products.owner.enabled',
        'include_global' => 'products.owner.include_global',
    ],
);
$counts = $runner->run(function ($owner) {
    // Runs inside OwnerContext::withOwner($owner)
    return Product::forOwner($owner)->count();
});

// forEach returns a collection with one entry per owner
$allResults = $runner->forEach(function ($owner) {
    return Product::forOwner($owner)->count();
});
```

**Features:**
- Iterates owners via distinct owner_type/owner_id from the model table
- Wraps each iteration in `OwnerContext::withOwner()`
- Pass `null` for `ownerConfig` to always iterate every owner tuple
- When `include_global` resolves truthy, iteration runs under `OwnerScopeOverride::withoutIncludeGlobal()` so per-owner work never picks up global rows
- `run()` returns reduced results (sum for ints, merged sums for arrays, otherwise the first non-null result)
- `forEach()` returns a Collection of all results

---

## Direct Injection

All actions support direct dependency injection. For example, in a Filament action:

```php
use AIArmada\CommerceSupport\Actions\ResolveOwnedModelOrFailAction;
use Filament\Actions\Action;

class EditProductAction extends Action
{
    public function __construct(
        private ResolveOwnedModelOrFailAction $resolveModel
    ) {
        parent::__construct();
    }

    public function action(): void
    {
        $product = $this->resolveModel->run(
            modelClass: Product::class,
            id: $this->record->id,
            owner: auth()->user(),
            includeGlobal: false
        );
        
        // Use $product...
    }
}
```

---

## Testing Actions

Test actions directly with focused, isolated tests:

```php
use AIArmada\CommerceSupport\Actions\ResolveOwnedModelOrFailAction;
use Tests\TestCase;

class ResolveOwnedModelActionTest extends TestCase
{
    #[Test]
    public function it_resolves_owned_model(): void
    {
        $owner = User::factory()->create();
        $product = Product::factory()->forOwner($owner)->create();

        $resolved = ResolveOwnedModelOrFailAction::run(
            modelClass: Product::class,
            id: $product->id,
            owner: $owner,
            includeGlobal: false
        );

        $this->assertTrue($resolved->is($product));
    }

    #[Test]
    public function it_throws_for_cross_tenant_access(): void
    {
        $owner1 = User::factory()->create();
        $owner2 = User::factory()->create();
        $product = Product::factory()->forOwner($owner1)->create();

        $this->expectException(AuthorizationException::class);

        ResolveOwnedModelOrFailAction::run(
            modelClass: Product::class,
            id: $product->id,
            owner: $owner2,
            includeGlobal: false
        );
    }
}
```

---

## Related Documentation

- [Multi-Tenancy & Owner Scoping](./04-multi-tenancy.md)
- [Traits & Utilities](./10-traits-utilities.md)
- [Isolation Primitives](./11-isolation-primitives.md)
- [Webhooks](./08-webhooks.md)
- [Laravel Actions Documentation](https://github.com/lorisleiva/laravel-actions)
