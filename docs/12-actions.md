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
| [ResolveOwnedModelOrFailAction](#resolveownedmodelorfailaction) | Owner-scoped model lookup with authorization | `::run(modelClass, value, owner, includeGlobal)` |
| [ResolveOwnerJobContextAction](#resolveownerjobcontextaction) | Extract owner context from queued jobs | `::run(job)` |
| [ProcessWebhookCallAction](#processwebhookcallaction) | Webhook transaction, deduplication, event extraction | `::run(webhookCall, extractEventType, isDuplicateProcessedEvent, processEvent)` |
| [UpsertEnvVariablesAction](#upsertenvvariablesaction) | Parse and upsert .env file key-value pairs | `::run(updates, force, basePath)` |
| [DiscoverCommercePublishTagsAction](#discovercommercepublishtagsaction) | Discover publish tags for configs + migrations | `::run(includeConfig)` |
| [DiscoverCommerceMigrationPublishTagsAction](#discovercommercemigrationpublishtagsaction) | Discover publish tags for migrations only | `::run()` |
| [ResolveProjectRootAction](#resolveprojectrootaction) | Detect project root in monorepo/testbench context | `::run()` |
| [EnsureCustomGuidelinesSymlinkAction](#ensurecustomguidelinessymlinkaction) | Create .ai/guidelines symlink for testbench | `::run(projectRoot, warn)` |

---

## ResolveOwnedModelOrFailAction

**Purpose:** Resolve a model instance within an owner scope with authorization checks.

**Use case:** Filament actions, API controllers, tests that need owner-scoped model lookup.

```php
use AIArmada\CommerceSupport\Actions\ResolveOwnedModelOrFailAction;
use AIArmada\Products\Models\Product;

$product = ResolveOwnedModelOrFailAction::run(
    modelClass: Product::class,
    value: $productId,
    owner: auth()->user(),
    includeGlobal: false // Set true to also include global (owner = null) records
);
```

**Throws:** `AuthorizationException` if model is not owned by the specified owner (when owner scoping is active).

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

$result = ProcessWebhookCallAction::run(
    webhookCall: $webhookCall,
    extractEventType: fn($payload) => $payload['event'],
    isDuplicateProcessedEvent: function($eventType, $eventId) {
        return Event::where('event_type', $eventType)
            ->where('external_id', $eventId)
            ->exists();
    },
    processEvent: function($eventType, $eventPayload) {
        // Process the webhook event
        Event::dispatch(new WebhookEventReceived($eventType, $eventPayload));
    }
);
```

**Guarantees:**
- Row-level lock prevents duplicate processing
- Event extraction happens before transaction
- Custom process callback runs inside transaction
- Automatic failure tracking

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
    basePath: base_path()
);
```

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
//   'AIArmada\\Products\\ProductServiceProvider' => ['commerce-products-migrations', 'commerce-products-config'],
//   'AIArmada\\Cart\\CartServiceProvider' => ['commerce-cart-migrations'],
//   ...
// ]
```

**Integration:** Used by `InstallCommand` and `PublishMigrationsCommand`.

---

## DiscoverCommerceMigrationPublishTagsAction

**Purpose:** Discover publish tags for migrations only (subset of above).

**Use case:** Commands that only need to publish migrations, not configs.

```php
use AIArmada\CommerceSupport\Actions\DiscoverCommerceMigrationPublishTagsAction;

$migrationTags = DiscoverCommerceMigrationPublishTagsAction::run();

// Result: array<class-string, array<int, string>>
// [
//   'AIArmada\\Products\\ProductServiceProvider' => ['commerce-products-migrations'],
//   'AIArmada\\Cart\\CartServiceProvider' => ['commerce-cart-migrations'],
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
            value: $this->record->id,
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
            value: $product->id,
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
            value: $product->id,
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
