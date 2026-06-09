<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Permission\PermissionRegistrar;

/**
 * @property string $id
 * @property string $scopeable_type
 * @property string $scopeable_id
 * @property string|null $label
 */
final class AuthzScope extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'scopeable_type',
        'scopeable_id',
        'label',
    ];

    public function getTable(): string
    {
        $tables = config('filament-authz.database.tables', []);
        $prefix = config('filament-authz.database.table_prefix', 'authz_');

        return $tables['authz_scopes'] ?? $prefix . 'scopes';
    }

    public function scopeable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        static::deleting(function (AuthzScope $authzScope): void {
            $teamsKey = app(PermissionRegistrar::class)->teamsKey;
            $roleClass = config('permission.models.role', Role::class);

            /** @var class-string<Model> $roleClass */
            $roleClass::query()
                ->where($teamsKey, $authzScope->getKey())
                ->update([$teamsKey => null]);
        });
    }
}
