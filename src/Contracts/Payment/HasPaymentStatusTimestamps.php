<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Payment;

/**
 * Interface for models that map PaymentStatus values to timestamp columns.
 *
 * Models using HasPaymentStatus can implement this interface to provide
 * a custom mapping from payment status values to their corresponding
 * timestamp column names.
 *
 * By default, only terminal/business-critical states get timestamp columns:
 * - PAID     -> paid_at
 * - REFUNDED -> refunded_at
 * - FAILED   -> failed_at
 * - CANCELLED -> cancelled_at
 *
 * @example
 * ```php
 * class Order extends Model implements HasPaymentStatusTimestamps
 * {
 *     use HasPaymentStatus;
 *
 *     public function getPaymentStatusTimestamps(): array
 *     {
 *         return [
 *             PaymentStatus::PAID->value => 'paid_at',
 *             PaymentStatus::REFUNDED->value => 'refunded_at',
 *             PaymentStatus::FAILED->value => 'failed_at',
 *             PaymentStatus::CANCELLED->value => 'cancelled_at',
 *         ];
 *     }
 * }
 * ```
 */
interface HasPaymentStatusTimestamps
{
    /**
     * Get the mapping of payment status values to timestamp column names.
     *
     * @return array<string, string> PaymentStatus value => timestamp column name
     */
    public function getPaymentStatusTimestamps(): array;
}
