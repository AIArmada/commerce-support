<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Provider-neutral notification that a remote payment refund has completed.
 *
 * Payment providers may dispatch their own richer event as well. This event is
 * the stable integration seam for packages that need to react without taking
 * a dependency on a particular provider.
 */
final class PaymentRefunded
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $provider,
        public readonly ?string $paymentId,
        public readonly ?string $relatedPaymentId,
        public readonly int $amount,
        public readonly string $currency,
        public readonly ?string $reference = null,
        public readonly array $metadata = [],
        public readonly array $payload = [],
    ) {}
}
