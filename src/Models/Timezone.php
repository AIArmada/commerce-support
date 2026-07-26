<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Timezone extends Model
{
    use HasUuids;

    protected $fillable = ['name'];

    public function getTable(): string
    {
        return config('commerce-support.database.tables.timezones', 'timezones');
    }
}
