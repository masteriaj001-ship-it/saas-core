<?php

declare(strict_types=1);

namespace App\Filament\Resources\CreditTransactionResource\Pages;

use App\Filament\Resources\CreditTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListCreditTransactions extends ListRecords
{
    protected static string $resource = CreditTransactionResource::class;
}
