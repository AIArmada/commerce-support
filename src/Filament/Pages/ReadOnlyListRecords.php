<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Filament\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

abstract class ReadOnlyListRecords extends ListRecords
{
    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
