<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Actions;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\WebhookClient\Models\WebhookCall;
use Throwable;

final class ProcessWebhookCallAction
{
    use AsAction;

    /**
     * Minutes after which a `processing` claim is considered stale (the worker
     * died without recording an outcome) and may be reclaimed.
     */
    private const STALE_PROCESSING_MINUTES = 30;

    /**
     * Canonical owner key for the dedup unique.
     *
     * Ownerless deliveries hash to a fixed sentinel instead of null: null
     * never collides in a unique index, so concurrent ownerless duplicates
     * would both claim and both process. The sentinel input is
     * domain-separated from 'owner:{type}|{id}' and cannot overlap a real
     * owner hash.
     *
     * @param  array{0: string|null, 1: string|null}  $owner
     */
    public static function ownerHashFor(array $owner): string
    {
        [$type, $id] = $owner;

        if ($type === null || $id === null) {
            return hash('sha256', 'commerce-support:ownerless-webhook-delivery');
        }

        return hash('sha256', 'owner:' . $type . '|' . $id);
    }

    public static function supportsOwnerDedup(): bool
    {
        return Schema::hasColumns((new WebhookCall)->getTable(), ['owner_type', 'owner_id', 'owner_hash']);
    }

    /**
     * Stable identity for deliveries without a provider event id.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function payloadHash(array $payload): string
    {
        return hash('sha256', (string) json_encode($payload));
    }

    /**
     * @param  callable(array<string, mixed>): string  $extractEventType
     * @param  callable(array<string, mixed>): (string|null)  $extractEventId
     * @param  callable(WebhookCall, array<string, mixed>, string): bool  $isDuplicateProcessedEvent
     * @param  callable(string, array<string, mixed>): void  $processEvent
     * @param  (callable(array<string, mixed>): array{0: string|null, 1: string|null})|null  $extractOwner
     */
    public function handle(
        WebhookCall $webhookCall,
        callable $extractEventType,
        callable $extractEventId,
        callable $isDuplicateProcessedEvent,
        callable $processEvent,
        ?callable $extractOwner = null,
    ): void {
        $claim = $this->claim($webhookCall, $extractEventType, $extractEventId, $extractOwner);

        if ($claim === null) {
            return;
        }

        [$id, $eventType, $payload] = $claim;

        try {
            $fresh = WebhookCall::query()->whereKey($id)->first();

            if (! $fresh instanceof WebhookCall) {
                return;
            }

            if ($isDuplicateProcessedEvent($fresh, $payload, $eventType)) {
                $this->markProcessedAsDuplicate($id);

                return;
            }

            $processEvent($eventType, $payload);

            $this->markProcessed($id);
        } catch (Throwable $e) {
            report($e);

            $failed = WebhookCall::query()->whereKey($id)->first();

            if ($failed instanceof WebhookCall) {
                $failed->update([
                    'status' => 'failed',
                    'failed_at' => CarbonImmutable::now(),
                    'exception' => [
                        'class' => $e::class,
                        'message' => Str::limit($e->getMessage(), 2000),
                    ],
                ]);
            }

            throw $e;
        }
    }

    /**
     * Claim the row for processing in a short row-locked transaction.
     *
     * Cross-delivery deduplication is enforced by the
     * UNIQUE(name, event_id, event_type, owner_hash) constraint: the loser
     * of a concurrent claim marks itself processed as a duplicate without
     * running side effects. Every stamped member is non-null — deliveries
     * without a provider event id fall back to a payload hash, and
     * deliveries without an owner identity hash to the ownerless sentinel
     * — because null never collides in a unique index. Sequential
     * redeliveries that arrive after the first delivery processed are
     * caught by the processed-row check instead.
     *
     * @param  callable(array<string, mixed>): string  $extractEventType
     * @param  callable(array<string, mixed>): (string|null)  $extractEventId
     * @param  (callable(array<string, mixed>): array{0: string|null, 1: string|null})|null  $extractOwner
     * @return array{int|string, string, array<string, mixed>}|null
     */
    private function claim(WebhookCall $webhookCall, callable $extractEventType, callable $extractEventId, ?callable $extractOwner = null): ?array
    {
        try {
            return DB::transaction(function () use ($webhookCall, $extractEventType, $extractEventId, $extractOwner): ?array {
                /** @var WebhookCall|null $locked */
                $locked = WebhookCall::query()
                    ->whereKey($webhookCall->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $locked instanceof WebhookCall) {
                    return null;
                }

                if ($locked->getAttribute('processed_at') !== null) {
                    return null;
                }

                if ($locked->getAttribute('status') === 'processing' && ! $this->isStaleClaim($locked)) {
                    return null;
                }

                /** @var array<string, mixed> $payload */
                $payload = $locked->payload ?? [];
                $eventType = $extractEventType($payload);
                $eventId = $extractEventId($payload) ?? self::payloadHash($payload);

                $attributes = [
                    'status' => 'processing',
                    'event_type' => $eventType,
                    'event_id' => $eventId,
                ];

                if (self::supportsOwnerDedup()) {
                    [$ownerType, $ownerId] = $extractOwner !== null
                        ? $extractOwner($payload)
                        : [null, null];

                    $attributes['owner_type'] = $ownerType;
                    $attributes['owner_id'] = $ownerId;
                    $attributes['owner_hash'] = self::ownerHashFor([$ownerType, $ownerId]);
                }

                $locked->update($attributes);

                return [$locked->getKey(), $eventType, $payload];
            });
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                $this->markProcessed($webhookCall->getKey());

                return null;
            }

            throw $e;
        }
    }

    private function isStaleClaim(WebhookCall $locked): bool
    {
        $updatedAt = $locked->getAttribute('updated_at');

        if (! $updatedAt instanceof CarbonImmutable && ! $updatedAt instanceof DateTimeInterface) {
            return true;
        }

        return CarbonImmutable::parse($updatedAt)->lessThan(
            CarbonImmutable::now()->subMinutes(self::STALE_PROCESSING_MINUTES)
        );
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return in_array((string) ($e->errorInfo[0] ?? $e->getCode()), ['23000', '23505'], true);
    }

    private function markProcessed(int | string $id): void
    {
        WebhookCall::query()
            ->whereKey($id)
            ->update([
                'status' => 'processed',
                'processed_at' => CarbonImmutable::now(),
            ]);
    }

    /**
     * Mark a duplicate delivery processed without keeping the claim stamps.
     *
     * The claim stamps the dedup identity (event_id/event_type/owner_*)
     * before the duplicate check runs. A duplicate carries no independent
     * delivery, so the stamps are cleared: consumers sharing this table key
     * their scopes off these columns (chip's Webhook model counts name +
     * non-null event_type rows), and a stamped duplicate would masquerade
     * as a real delivery.
     */
    private function markProcessedAsDuplicate(int | string $id): void
    {
        $attributes = [
            'status' => 'processed',
            'processed_at' => CarbonImmutable::now(),
            'event_id' => null,
            'event_type' => null,
        ];

        if (self::supportsOwnerDedup()) {
            $attributes['owner_type'] = null;
            $attributes['owner_id'] = null;
            $attributes['owner_hash'] = null;
        }

        WebhookCall::query()->whereKey($id)->update($attributes);
    }
}
