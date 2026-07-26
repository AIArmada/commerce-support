<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    use HasUuids;

    protected $fillable = ['code', 'name', 'native', 'dir'];

    public function getTable(): string
    {
        return config('commerce-support.database.tables.languages', 'languages');
    }
}
