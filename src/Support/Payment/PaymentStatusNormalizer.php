<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support\Payment;

use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;

/**
 * Normalizes gateway-specific payment status strings into the canonical PaymentStatus enum.
 *
 * Gateway packages can use this directly or register additional mappings for
 * gateway-specific status strings via `registerMapping()`.
 *
 * Default mappings cover common patterns across Stripe, Chip, and similar gateways.
 */
final class PaymentStatusNormalizer
{
    /** @var array<string, PaymentStatus> */
    private array $mappings;

    public function __construct()
    {
        $this->mappings = $this->defaultMappings();
    }

    /**
     * @return array<string, PaymentStatus>
     */
    private function defaultMappings(): array
    {
        return [
            'created' => PaymentStatus::CREATED,
            'sent' => PaymentStatus::PENDING,
            'viewed' => PaymentStatus::PENDING,
            'pending' => PaymentStatus::PENDING,
            'pending_execute' => PaymentStatus::PENDING,
            'pending_charge' => PaymentStatus::PENDING,
            'pending_capture' => PaymentStatus::AUTHORIZED,
            'pending_release' => PaymentStatus::AUTHORIZED,
            'hold' => PaymentStatus::AUTHORIZED,
            'preauthorized' => PaymentStatus::AUTHORIZED,
            'requires_action' => PaymentStatus::REQUIRES_ACTION,
            'requires_confirmation' => PaymentStatus::REQUIRES_ACTION,
            'requires_payment_method' => PaymentStatus::REQUIRES_ACTION,
            'processing' => PaymentStatus::PROCESSING,
            'attempted_capture' => PaymentStatus::PROCESSING,
            'attempted_refund' => PaymentStatus::PROCESSING,
            'attempted_recurring' => PaymentStatus::PROCESSING,
            'pending_refund' => PaymentStatus::PROCESSING,
            'authorized' => PaymentStatus::AUTHORIZED,
            'paid' => PaymentStatus::PAID,
            'captured' => PaymentStatus::PAID,
            'paid_authorized' => PaymentStatus::PAID,
            'recurring_successful' => PaymentStatus::PAID,
            'cleared' => PaymentStatus::PAID,
            'settled' => PaymentStatus::PAID,
            'succeeded' => PaymentStatus::PAID,
            'completed' => PaymentStatus::PAID,
            'refunded' => PaymentStatus::REFUNDED,
            'partially_refunded' => PaymentStatus::PARTIALLY_REFUNDED,
            'cancelled' => PaymentStatus::CANCELLED,
            'canceled' => PaymentStatus::CANCELLED,
            'released' => PaymentStatus::CANCELLED,
            'expired' => PaymentStatus::EXPIRED,
            'overdue' => PaymentStatus::EXPIRED,
            'chargeback' => PaymentStatus::DISPUTED,
            'disputed' => PaymentStatus::DISPUTED,
            'error' => PaymentStatus::FAILED,
            'failed' => PaymentStatus::FAILED,
            'blocked' => PaymentStatus::FAILED,
        ];
    }

    /**
     * Register or override a mapping from gateway status string to PaymentStatus.
     */
    public function registerMapping(string $gatewayStatus, PaymentStatus $status): self
    {
        $this->mappings[$gatewayStatus] = $status;

        return $this;
    }

    /**
     * Register multiple mappings at once.
     *
     * @param  array<string, PaymentStatus>  $mappings
     */
    public function registerMappings(array $mappings): self
    {
        foreach ($mappings as $gatewayStatus => $status) {
            $this->mappings[$gatewayStatus] = $status;
        }

        return $this;
    }

    /**
     * Normalize a gateway status string to a PaymentStatus.
     *
     * Falls back to PaymentStatus::PENDING for unknown status strings.
     */
    public function normalize(string $gatewayStatus): PaymentStatus
    {
        $key = mb_strtolower(mb_trim($gatewayStatus));

        return $this->mappings[$key] ?? PaymentStatus::PENDING;
    }

    /**
     * Alias for normalize().
     */
    public function map(string $gatewayStatus): PaymentStatus
    {
        return $this->normalize($gatewayStatus);
    }
}
