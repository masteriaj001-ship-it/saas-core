<?php

declare(strict_types=1);

namespace App\Filament\Resources\CashShiftResource\Pages;

use App\Filament\Resources\CashShiftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCashShifts extends ListRecords
{
    protected static string $resource = CashShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
