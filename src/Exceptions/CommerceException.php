<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for all AIArmada Commerce packages.
 *
 * This exception serves as the foundation for all commerce-related exceptions,
 * providing a consistent interface and error handling across the monorepo.
 */
class CommerceException extends Exception
{
    /**
     * Create a new Commerce exception.
     *
     * @param  string  $message  The exception message
     * @param  string|null  $errorCode  Optional error code for categorization
     * @param  array<string, mixed>  $errorData  Additional error context data
     * @param  int  $code  The exception code
     * @param  Throwable|null  $previous  The previous throwable used for exception chaining
     */
    public function __construct(
        string $message,
        protected ?string $errorCode = null,
        protected array $errorData = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the error code associated with this exception.
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Get additional error data associated with this exception.
     *
     * @return array<string, mixed>
     */
    public function getErrorData(): array
    {
        return $this->errorData;
    }

    /**
     * Get the full error context including message, code, and data.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'error_code' => $this->errorCode,
            'data' => $this->errorData,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }
}
