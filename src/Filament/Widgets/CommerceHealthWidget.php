<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Filament\Widgets;

use Filament\Widgets\Widget;
use Spatie\Health\Facades\Health;
use Spatie\Health\ResultStores\ResultStore;
use Throwable;

/**
 * Filament widget displaying the health status of commerce services.
 *
 * Shows the current status of all registered health checks including:
 * - CHIP Payment Gateway
 * - J&T Shipping
 * - Low Stock alerts
 * - Order Processing status
 */
class CommerceHealthWidget extends Widget
{
    protected string $view = 'commerce-support::widgets.health-status';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        // Only show if health checks are available
        return class_exists(Health::class)
            && app()->bound('health');
    }

    /**
     * Get the current health check results.
     *
     * @return array<int, array{name: string, status: string, message: string, meta: array<string, mixed>}>
     */
    public function getHealthResults(): array
    {
        if (! self::canView()) {
            return [];
        }

        try {
            $resultStore = app(ResultStore::class);
            $storedResults = $resultStore->latestResults();
        } catch (Throwable) {
            // Run checks directly if no stored results
            $storedResults = collect();
        }

        $results = [];

        foreach ($storedResults as $checkResult) {
            $results[] = [
                'name' => $checkResult->check,
                'label' => $this->formatCheckName($checkResult->check),
                'status' => $this->mapStatus($checkResult->status),
                'message' => $checkResult->shortSummary,
                'meta' => $checkResult->meta,
                'ended_at' => $checkResult->ended_at,
            ];
        }

        return $results;
    }

    /**
     * Get the overall health status.
     *
     * @return array{status: string, color: string, icon: string}
     */
    public function getOverallStatus(): array
    {
        $results = $this->getHealthResults();

        if (empty($results)) {
            return [
                'status' => 'unknown',
                'color' => 'gray',
                'icon' => 'heroicon-o-question-mark-circle',
            ];
        }

        $hasFailure = collect($results)->contains('status', 'failed');
        $hasWarning = collect($results)->contains('status', 'warning');

        if ($hasFailure) {
            return [
                'status' => 'unhealthy',
                'color' => 'danger',
                'icon' => 'heroicon-o-x-circle',
            ];
        }

        if ($hasWarning) {
            return [
                'status' => 'degraded',
                'color' => 'warning',
                'icon' => 'heroicon-o-exclamation-triangle',
            ];
        }

        return [
            'status' => 'healthy',
            'color' => 'success',
            'icon' => 'heroicon-o-check-circle',
        ];
    }

    /**
     * Get the count of checks by status.
     *
     * @return array{ok: int, warning: int, failed: int, skipped: int}
     */
    public function getStatusCounts(): array
    {
        $results = $this->getHealthResults();

        return [
            'ok' => collect($results)->where('status', 'ok')->count(),
            'warning' => collect($results)->where('status', 'warning')->count(),
            'failed' => collect($results)->where('status', 'failed')->count(),
            'skipped' => collect($results)->where('status', 'skipped')->count(),
        ];
    }

    /**
     * Get color for a status.
     */
    public function getStatusColor(string $status): string
    {
        return match ($status) {
            'ok' => 'success',
            'warning' => 'warning',
            'failed' => 'danger',
            'skipped' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get icon for a status.
     */
    public function getStatusIcon(string $status): string
    {
        return match ($status) {
            'ok' => 'heroicon-o-check-circle',
            'warning' => 'heroicon-o-exclamation-triangle',
            'failed' => 'heroicon-o-x-circle',
            'skipped' => 'heroicon-o-minus-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Format check class name to human-readable label.
     */
    protected function formatCheckName(string $checkName): string
    {
        // Remove namespace and "Check" suffix
        $name = class_basename($checkName);
        $name = preg_replace('/Check$/', '', $name);

        // Split camelCase
        return implode(' ', preg_split('/(?=[A-Z])/', $name, -1, PREG_SPLIT_NO_EMPTY));
    }

    /**
     * Map Spatie Health status to simple string.
     */
    protected function mapStatus(string $status): string
    {
        return match ($status) {
            'ok' => 'ok',
            'warning' => 'warning',
            'failed' => 'failed',
            'crashed' => 'failed',
            'skipped' => 'skipped',
            default => 'unknown',
        };
    }
}
