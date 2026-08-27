<?php

declare(strict_types=1);

namespace App\Filament\Resources\CreditAccountResource\Pages;

use App\Filament\Resources\CreditAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCreditAccounts extends ListRecords
{
    protected static string $resource = CreditAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
