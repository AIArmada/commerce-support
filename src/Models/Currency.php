<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'symbol_native',
        'precision',
        'symbol_first',
        'decimal_mark',
        'thousands_separator',
    ];

    public function getTable(): string
    {
        return config('commerce-support.database.tables.currencies', 'currencies');
    }

    protected function casts(): array
    {
        return [
            'precision' => 'integer',
            'symbol_first' => 'boolean',
        ];
    }
}
