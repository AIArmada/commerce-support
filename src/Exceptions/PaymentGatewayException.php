<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Exceptions;

use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;
use Throwable;

/**
 * Exception thrown when a payment gateway operation fails.
 */
class PaymentGatewayException extends CommerceException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly string $gatewayName,
        ?string $errorCode = null,
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            message: $message,
            errorCode: $errorCode,
            errorData: array_merge(['gateway' => $gatewayName], $context),
            code: $code,
            previous: $previous
        );
    }

    /**
     * Create exception for payment creation failure.
     *
     * @param  array<string, mixed>  $context
     */
    public static function creationFailed(
        string $gatewayName,
        string $message,
        ?string $errorCode = null,
        array $context = [],
        ?Throwable $previous = null
    ): self {
        return new self(
            message: "Failed to create payment: {$message}",
            gatewayName: $gatewayName,
            errorCode: $errorCode,
            context: $context,
            previous: $previous
        );
    }

    /**
     * Create exception for payment not found.
     */
    public static function notFound(string $gatewayName, string $paymentId): self
    {
        return new self(
            message: "Payment not found: {$paymentId}",
            gatewayName: $gatewayName,
            errorCode: 'payment_not_found',
            context: ['payment_id' => $paymentId]
        );
    }

    /**
     * Create exception for refund failure.
     *
     * @param  array<string, mixed>  $context
     */
    public static function refundFailed(
        string $gatewayName,
        string $paymentId,
        string $message,
        array $context = [],
        ?Throwable $previous = null
    ): self {
        return new self(
            message: "Failed to refund payment {$paymentId}: {$message}",
            gatewayName: $gatewayName,
            errorCode: 'refund_failed',
            context: array_merge(['payment_id' => $paymentId], $context),
            previous: $previous
        );
    }

    /**
     * Create exception for capture failure.
     */
    public static function captureFailed(
        string $gatewayName,
        string $paymentId,
        string $message,
        ?Throwable $previous = null
    ): self {
        return new self(
            message: "Failed to capture payment {$paymentId}: {$message}",
            gatewayName: $gatewayName,
            errorCode: 'capture_failed',
            context: ['payment_id' => $paymentId],
            previous: $previous
        );
    }

    /**
     * Create exception for cancellation failure.
     */
    public static function cancellationFailed(
        string $gatewayName,
        string $paymentId,
        string $message,
        ?Throwable $previous = null
    ): self {
        return new self(
            message: "Failed to cancel payment {$paymentId}: {$message}",
            gatewayName: $gatewayName,
            errorCode: 'cancellation_failed',
            context: ['payment_id' => $paymentId],
            previous: $previous
        );
    }

    /**
     * Create exception for invalid configuration.
     */
    public static function invalidConfiguration(string $gatewayName, string $message): self
    {
        return new self(
            message: "Invalid gateway configuration: {$message}",
            gatewayName: $gatewayName,
            errorCode: 'invalid_configuration'
        );
    }

    /**
     * Create exception for unsupported operation.
     */
    public static function unsupportedOperation(string $gatewayName, string $operation): self
    {
        return new self(
            message: "Operation '{$operation}' is not supported by {$gatewayName}",
            gatewayName: $gatewayName,
            errorCode: 'unsupported_operation',
            context: ['operation' => $operation]
        );
    }

    /**
     * Create exception for currency mismatch.
     */
    public static function currencyMismatch(
        string $gatewayName,
        string $expected,
        string $actual
    ): self {
        return new self(
            message: "Currency mismatch: expected {$expected}, got {$actual}",
            gatewayName: $gatewayName,
            errorCode: 'currency_mismatch',
            context: ['expected' => $expected, 'actual' => $actual]
        );
    }

    /**
     * Create exception for invalid status transition.
     *
     * @param  PaymentStatus  $from
     * @param  PaymentStatus  $to
     * @param  array<PaymentStatus>  $allowed
     */
    public static function invalidStatusTransition(
        $from,
        $to,
        array $allowed = []
    ): self {
        $allowedNames = array_map(fn ($s) => $s->value, $allowed);
        $allowedStr = empty($allowedNames) ? 'none' : implode(', ', $allowedNames);

        return new self(
            message: "Invalid payment status transition from '{$from->value}' to '{$to->value}'. Allowed transitions: {$allowedStr}",
            gatewayName: 'internal',
            errorCode: 'invalid_status_transition',
            context: [
                'from' => $from->value,
                'to' => $to->value,
                'allowed' => $allowedNames,
            ]
        );
    }
}
