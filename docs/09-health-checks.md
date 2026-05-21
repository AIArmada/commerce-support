---
title: Health Checks
---

# Health Checks

Commerce Support provides health check infrastructure built on `spatie/laravel-health`. This allows commerce packages to expose their operational status to monitoring systems and admin dashboards.

## Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│                     Health Check Flow                             │
├──────────────────────────────────────────────────────────────────┤
│                                                                   │
│   CommerceHealthCheck (base)                                      │
│        │                                                          │
│        ├──► CartHealthCheck                                       │
│        ├──► InventoryHealthCheck                                  │
│        ├──► PaymentGatewayHealthCheck                             │
│        └──► YourCustomHealthCheck                                 │
│                                                                   │
│   CommerceHealthWidget (Filament)                                 │
│        │                                                          │
│        └──► Displays all registered checks in dashboard           │
│                                                                   │
└──────────────────────────────────────────────────────────────────┘
```

## Creating Health Checks

### Extend Base Class

`CommerceHealthCheck` owns the final `run()` method and converts thrown exceptions into failed results. Concrete checks should implement `performCheck()`.

```php
use AIArmada\CommerceSupport\Health\CommerceHealthCheck;
use Spatie\Health\Checks\Result;

class CartHealthCheck extends CommerceHealthCheck
{
    public ?string $name = 'Cart System';

    protected function performCheck(): Result
    {
        // Check abandoned cart count
        $abandonedCount = Cart::where('status', 'abandoned')
            ->where('updated_at', '<', now()->subDays(7))
            ->count();

        if ($abandonedCount > 1000) {
            return Result::make()
                ->failed("High abandoned cart count: {$abandonedCount}");
        }

        if ($abandonedCount > 500) {
            return Result::make()
                ->warning("Elevated abandoned carts: {$abandonedCount}");
        }

        return Result::make()
            ->ok("Abandoned carts: {$abandonedCount}");
    }
}
```

### Implement HasHealthCheck Interface

For models that need health monitoring:

```php
use AIArmada\CommerceSupport\Contracts\HasHealthCheck;
use Spatie\Health\Checks\Result;

class PaymentGateway implements HasHealthCheck
{
    public function healthCheck(): Result
    {
        try {
            $response = Http::timeout(5)
                ->get($this->getApiStatusUrl());

            if ($response->successful()) {
                return Result::make()->ok('Gateway operational');
            }

            return Result::make()
                ->failed("Gateway returned: {$response->status()}");

        } catch (\Exception $e) {
            return Result::make()
                ->failed("Gateway unreachable: {$e->getMessage()}");
        }
    }
}
```

## Registering Health Checks

### In Service Provider

```php
use Spatie\Health\Facades\Health;
use Spatie\Health\Checks\Checks\DatabaseCheck;

public function boot(): void
{
    Health::checks([
        DatabaseCheck::new(),
        CartHealthCheck::new(),
        InventoryHealthCheck::new(),
        PaymentGatewayHealthCheck::new(),
    ]);
}
```

### Conditional Registration

```php
public function boot(): void
{
    $checks = [DatabaseCheck::new()];

    if (class_exists(\AIArmada\Cart\CartServiceProvider::class)) {
        $checks[] = CartHealthCheck::new();
    }

    if (class_exists(\AIArmada\Inventory\InventoryServiceProvider::class)) {
        $checks[] = InventoryHealthCheck::new();
    }

    Health::checks($checks);
}
```

## Health Check Examples

### Inventory Stock Check

```php
class InventoryHealthCheck extends CommerceHealthCheck
{
    public ?string $name = 'Inventory System';

    protected function performCheck(): Result
    {
        $lowStockCount = StockLevel::where('quantity', '<=', 5)
            ->where('quantity', '>', 0)
            ->count();

        $outOfStockCount = StockLevel::where('quantity', '<=', 0)->count();

        if ($outOfStockCount > 100) {
            return Result::make()
                ->failed("Critical: {$outOfStockCount} items out of stock");
        }

        if ($lowStockCount > 50) {
            return Result::make()
                ->warning("Low stock on {$lowStockCount} items, {$outOfStockCount} out of stock");
        }

        return Result::make()
            ->ok("Stock levels healthy");
    }
}
```

### Payment Gateway Check

```php
class PaymentGatewayHealthCheck extends CommerceHealthCheck
{
    public ?string $name = 'Payment Gateway';

    protected function performCheck(): Result
    {
        $recentFailures = PaymentAttempt::where('status', 'failed')
            ->where('created_at', '>', now()->subHour())
            ->count();

        $totalAttempts = PaymentAttempt::where('created_at', '>', now()->subHour())
            ->count();

        if ($totalAttempts === 0) {
            return Result::make()->ok('No payment attempts in last hour');
        }

        $failureRate = ($recentFailures / $totalAttempts) * 100;

        if ($failureRate > 50) {
            return Result::make()
                ->failed("High failure rate: {$failureRate}%");
        }

        if ($failureRate > 20) {
            return Result::make()
                ->warning("Elevated failure rate: {$failureRate}%");
        }

        return Result::make()
            ->ok("Payment success rate: " . (100 - $failureRate) . "%");
    }
}
```

### Order Processing Check

```php
class OrderProcessingHealthCheck extends CommerceHealthCheck
{
    public ?string $name = 'Order Processing';

    protected function performCheck(): Result
    {
        // Check for stuck orders
        $stuckOrders = Order::where('status', 'processing')
            ->where('updated_at', '<', now()->subHours(2))
            ->count();

        // Check pending orders queue
        $pendingOrders = Order::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(30))
            ->count();

        if ($stuckOrders > 0) {
            return Result::make()
                ->failed("{$stuckOrders} orders stuck in processing");
        }

        if ($pendingOrders > 50) {
            return Result::make()
                ->warning("{$pendingOrders} orders pending over 30 minutes");
        }

        return Result::make()
            ->ok('Order processing healthy');
    }
}
```

## Filament Health Widget

### CommerceHealthWidget

Display health status in Filament dashboard:

```php
use AIArmada\CommerceSupport\Filament\Widgets\CommerceHealthWidget;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->widgets([
                CommerceHealthWidget::class,
            ]);
    }
}
```

### Widget Features

- Displays all registered health checks
- Color-coded status (green/yellow/red)
- Auto-refresh capability
- Click to view details
- Explicit gate authorization via `commerce-support.health.view_ability`

### Customizing the Widget

```php
class CommerceHealthWidget extends Widget
{
    protected static string $view = 'commerce-support::filament.widgets.health';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $ability = config('commerce-support.health.view_ability', 'viewCommerceHealth');

        return auth()->user()?->can($ability) ?? false;
    }

    protected function getPollingInterval(): ?string
    {
        return '30s'; // Auto-refresh every 30 seconds
    }
}
```

Configure the required ability in `config/commerce-support.php`:

```php
'health' => [
    'view_ability' => 'viewCommerceHealth',
],
```

## HTTP Endpoints

### Health Check Endpoint

```php
// routes/web.php
use Spatie\Health\Http\Controllers\HealthCheckResultsController;

Route::get('health', HealthCheckResultsController::class);
```

### JSON Response

```json
{
    "finishedAt": "2024-01-15T10:30:00Z",
    "checkResults": [
        {
            "name": "Database",
            "label": "Database",
            "status": "ok",
            "notificationMessage": "",
            "shortSummary": "Connected"
        },
        {
            "name": "CartHealthCheck",
            "label": "Cart System",
            "status": "ok",
            "notificationMessage": "",
            "shortSummary": "Abandoned carts: 125"
        }
    ]
}
```

## Scheduling Health Checks

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('health:check')->everyMinute();

    // Store results for historical tracking
    $schedule->command('health:schedule-check-heartbeat')->everyMinute();
}
```

## Notifications

### Configure Notifications

```php
// config/health.php
return [
    'notifications' => [
        'enabled' => true,
        'notifications' => [
            Spatie\Health\Notifications\CheckFailedNotification::class => ['slack'],
        ],
        'notifiable' => Spatie\Health\Notifications\Notifiable::class,
        'throttle_notifications_for_minutes' => 60,
    ],
];
```

### Custom Notification

```php
class CommerceHealthNotification extends Notification
{
    public function __construct(
        protected Result $result
    ) {}

    public function via($notifiable): array
    {
        return ['slack', 'mail'];
    }

    public function toSlack($notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->error()
            ->content("Commerce Health Alert: {$this->result->label}")
            ->attachment(function ($attachment) {
                $attachment
                    ->title($this->result->status)
                    ->content($this->result->notificationMessage);
            });
    }
}
```

## Best Practices

1. **Keep checks fast** - Health checks should complete in under 1 second
2. **Use meaningful thresholds** - Differentiate between warning and failure states
3. **Check external dependencies** - Include API, database, queue checks
4. **Schedule regular checks** - Run checks at least every minute
5. **Set up alerts** - Notify on failures, not just warnings
6. **Track history** - Store results for trend analysis
7. **Limit widget access** - Only admins should see health status
