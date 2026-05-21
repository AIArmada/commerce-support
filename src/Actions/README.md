<!-- This file provides quick navigation to Actions documentation -->

# Laravel Actions

This directory contains reusable [Laravel Actions](https://github.com/lorisleiva/laravel-actions) that extract orchestration patterns across Commerce Support.

## Quick Reference

| Action | Purpose |
|--------|---------|
| `ResolveOwnedModelOrFailAction` | Owner-scoped model lookup with authorization |
| `ResolveOwnerJobContextAction` | Extract owner context from queued jobs |
| `ProcessWebhookCallAction` | Webhook transaction, deduplication, event extraction |
| `UpsertEnvVariablesAction` | Parse and upsert .env file key-value pairs |
| `DiscoverCommercePublishTagsAction` | Discover publish tags for configs + migrations |
| `DiscoverCommerceMigrationPublishTagsAction` | Discover publish tags for migrations only |
| `ResolveProjectRootAction` | Detect project root in monorepo/testbench context |
| `EnsureCustomGuidelinesSymlinkAction` | Create .ai/guidelines symlink for testbench |

## Documentation

Full documentation, usage examples, and testing patterns available at:
📖 [Laravel Actions Guide](../docs/12-actions.md)

## Pattern

All actions use the `AsAction` trait with a static `::run()` entry point:

```php
use AIArmada\CommerceSupport\Actions\ResolveOwnedModelOrFailAction;

$model = ResolveOwnedModelOrFailAction::run(...$args);
```

This pattern enables:
- Consistent, discoverable entry points
- Automatic dependency injection
- Easy testing in isolation
- Reusability across multiple call sites (commands, controllers, events, jobs)

## Integration

Actions are integrated into:
- **Commands:** Install, Setup, PublishMigrations, Boost
- **Traits:** OwnerWriteGuard, OwnerContextJob
- **Processors:** CommerceWebhookProcessor
- **Middleware:** OwnerIdentification

## Related Patterns

- Commands now delegate complex logic to actions (thin entry points)
- Traits resolve dependencies through actions
- Tests can invoke actions directly or through their delegators
