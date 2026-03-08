<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Health;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Throwable;

/**
 * Base health check for commerce services.
 *
 * Provides common patterns for health checking external services.
 *
 * @example
 * ```php
 * class ChipGatewayCheck extends CommerceHealthCheck
 * {
 *     public string $name = 'CHIP Payment Gateway';
 *
 *     protected function performCheck(): Result
 *     {
 *         $response = Http::get('https://gate.chip-in.asia/health');
 *
 *         if ($response->successful()) {
 *             return Result::make()->ok('CHIP gateway is operational');
 *         }
 *
 *         return Result::make()->failed('CHIP gateway is down');
 *     }
 * }
 * ```
 */
abstract class CommerceHealthCheck extends Check
{
    /**
     * The name of the health check.
     */
    public ?string $name = 'Commerce Health Check';

    /**
     * Perform the actual health check logic.
     */
    abstract protected function performCheck(): Result;

    /**
     * Run the health check.
     */
    final public function run(): Result
    {
        try {
            return $this->performCheck();
        } catch (Throwable $e) {
            return Result::make()
                ->failed("Health check failed: {$e->getMessage()}");
        }
    }

    /**
     * Create a successful result with optional meta data.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function success(string $message = 'OK', array $meta = []): Result
    {
        $result = Result::make()->ok($message);

        foreach ($meta as $key => $value) {
            $result->meta([$key => $value]);
        }

        return $result;
    }

    /**
     * Create a failed result with optional meta data.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function failure(string $message, array $meta = []): Result
    {
        $result = Result::make()->failed($message);

        foreach ($meta as $key => $value) {
            $result->meta([$key => $value]);
        }

        return $result;
    }

    /**
     * Create a warning result.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function warning(string $message, array $meta = []): Result
    {
        $result = Result::make()->warning($message);

        foreach ($meta as $key => $value) {
            $result->meta([$key => $value]);
        }

        return $result;
    }
}
