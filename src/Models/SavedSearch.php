<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_type
 * @property string $user_id
 * @property string|null $searchable_type
 * @property string|null $searchable_id
 * @property string $name
 * @property array|null $query
 * @property array|null $filters
 * @property array|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $user
 * @property-read Model|Eloquent|null $searchable
 */
final class SavedSearch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_type', 'user_id',
        'searchable_type', 'searchable_id',
        'name',
        'query', 'filters',
        'meta',
    ];

    public function getTable(): string
    {
        return config('commerce-support.database.tables.saved_searches', 'saved_searches');
    }

    protected function casts(): array
    {
        return [
            'query' => 'array',
            'filters' => 'array',
            'meta' => 'array',
        ];
    }

    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    public function searchable(): MorphTo
    {
        return $this->morphTo();
    }
}
