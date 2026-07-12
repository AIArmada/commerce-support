<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_type
 * @property string $user_id
 * @property string|null $category
 * @property string $channel
 * @property bool $is_enabled
 * @property string|null $quiet_hours_start
 * @property string|null $quiet_hours_end
 * @property string $digest_frequency
 * @property string|null $digest_delivery_time
 * @property array|null $channels_ordering
 * @property array|null $fallback_channels
 * @property array|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $user
 */
final class NotificationPreference extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'user_type', 'user_id',
        'category', 'channel',
        'is_enabled',
        'quiet_hours_start', 'quiet_hours_end',
        'digest_frequency', 'digest_delivery_time',
        'channels_ordering', 'fallback_channels',
        'meta',
    ];

    public function getTable(): string
    {
        return config('commerce-support.database.tables.notification_preferences', 'notification_preferences');
    }

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'quiet_hours_start' => 'string',
            'quiet_hours_end' => 'string',
            'digest_delivery_time' => 'string',
            'channels_ordering' => 'array',
            'fallback_channels' => 'array',
            'meta' => 'array',
        ];
    }

    public function user(): MorphTo
    {
        return $this->morphTo();
    }
}
