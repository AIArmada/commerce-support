@if (\Composer\InstalledVersions::getRootPackage()['name'] !== 'aiarmada/commerce')
## Commerce Support

- Use `Data\RecordReference` for persisted identities across package contracts. Convert Eloquent models at the application or driver boundary using `Support\EloquentRecordReference`.
- Host tenancy resolvers implement `Contracts\OwnerResolverInterface::resolve(): ?RecordReference`. Null is unresolved; it does not grant global access.
- Re-enter trusted owner context with `Support\OwnerContext::withOwner()` and enforce scoped writes. Only explicitly authorized system work may use a null owner.
- Queued work implements `Contracts\OwnerScopedJob` and returns `Data\OwnerJobContext`; `Traits\OwnerContextJob` restores the trusted owner before executing `performJob()`.
- Use `Persistence\OwnerCache`, `Persistence\OwnerFilesystem`, and `Persistence\VersionedCache` rather than constructing ad hoc tenant cache keys or paths.
- Reference-data operations go through `Features\ReferenceData\Api\ReferenceDataApi`. Reporting and UserPreferences own their own table writes.
- Webhooks owns the `webhook_calls` writes; extend `Features\Webhooks\EntryPoints\CommerceWebhookProcessor` for provider jobs. Use `Features\SignedDownloads\Api\SignedDownloadApi` for owner-bound temporary download issuance and authorization.
- Payment subject contracts carry record identities and actor data rather than Eloquent models. Convert incoming HTTP requests to `Data\WebhookRequestData` with `EntryPoints\Http\WebhookRequestMapper` before entering payment webhook contracts.
- Outbound HTTP uses validated public targets and the pinned HTTP driver where untrusted URLs are involved. Never bypass DNS/IP validation or enable unvalidated redirects.
@endif
