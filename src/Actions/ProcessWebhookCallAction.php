<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Actions;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\WebhookClient\Models\WebhookCall;
use Throwable;

final class ProcessWebhookCallAction
{
    use AsAction;

    /**
     * @param  callable(array<string, mixed>): string  $extractEventType
     * @param  callable(WebhookCall, array<string, mixed>, string): bool  $isDuplicateProcessedEvent
     * @param  callable(string, array<string, mixed>): void  $processEvent
     */
    public function handle(
        WebhookCall $webhookCall,
        callable $extractEventType,
        callable $isDuplicateProcessedEvent,
        callable $processEvent,
    ): void {
        try {
            DB::transaction(function () use ($webhookCall, $extractEventType, $isDuplicateProcessedEvent, $processEvent): void {
                /** @var WebhookCall|null $locked */
                $locked = WebhookCall::query()
                    ->whereKey($webhookCall->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $locked instanceof WebhookCall) {
                    return;
                }

                if ($locked->getAttribute('processed_at') !== null) {
                    return;
                }

                /** @var array<string, mixed> $payload */
                $payload = $locked->payload;
                $eventType = $extractEventType($payload);

                if ($isDuplicateProcessedEvent($locked, $payload, $eventType)) {
                    $locked->update([
                        'status' => 'processed',
                        'processed_at' => now(),
                    ]);

                    return;
                }

                $processEvent($eventType, $payload);

                $locked->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            $webhookCall->update([
                'status' => 'failed',
                'failed_at' => now(),
                'exception' => (string) $e,
            ]);

            throw $e;
        }
    }
}
