<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Exceptions;

use Throwable;

/**
 * Exception for API integration errors across commerce packages.
 *
 * This exception is designed for external API integrations (payment gateways,
 * shipping providers, etc.) and provides detailed context about failed API calls.
 *
 * Combines patterns from CHIP and JNT packages for comprehensive error reporting.
 */
class CommerceApiException extends CommerceException
{
    /**
     * Create a new API exception.
     *
     * @param  string  $message  The exception message
     * @param  int  $statusCode  HTTP status code from the API response
     * @param  array<string, mixed>  $errorData  Additional error data from the API
     * @param  string|null  $endpoint  The API endpoint that was called
     * @param  mixed  $apiResponse  The raw API response for debugging
     * @param  string|null  $errorCode  Optional error code from the API
     * @param  Throwable|null  $previous  The previous throwable used for exception chaining
     */
    public function __construct(
        string $message,
        protected int $statusCode = 0,
        array $errorData = [],
        protected ?string $endpoint = null,
        protected mixed $apiResponse = null,
        ?string $errorCode = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $errorData, $statusCode, $previous);
    }

    /**
     * Create an exception from an API response array.
     *
     * This factory method provides a convenient way to construct API exceptions
     * from standardized API response data.
     *
     * @param  array<string, mixed>  $responseData  The API response data
     * @param  int  $statusCode  HTTP status code
     * @param  string|null  $endpoint  The API endpoint that was called
     */
    public static function fromResponse(
        array $responseData,
        int $statusCode,
        ?string $endpoint = null
    ): static {
        $message = $responseData['message']
            ?? $responseData['error']
            ?? $responseData['error_description']
            ?? 'API request failed';

        $errorCode = $responseData['error_code']
            ?? $responseData['code']
            ?? null;

        // Remove message/error from data to avoid duplication
        $errorData = array_diff_key($responseData, array_flip([
            'message',
            'error',
            'error_description',
            'error_code',
            'code',
        ]));

        return new static( // @phpstan-ignore new.static
            message: $message,
            statusCode: $statusCode,
            errorData: $errorData,
            endpoint: $endpoint,
            apiResponse: $responseData,
            errorCode: $errorCode
        );
    }

    /**
     * Get the HTTP status code from the API response.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the API endpoint that was called.
     */
    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    /**
     * Get the raw API response.
     */
    public function getApiResponse(): mixed
    {
        return $this->apiResponse;
    }

    /**
     * {@inheritDoc}
     */
    public function getContext(): array
    {
        return array_merge(parent::getContext(), [
            'status_code' => $this->statusCode,
            'endpoint' => $this->endpoint,
            'api_response' => $this->apiResponse,
        ]);
    }
}
