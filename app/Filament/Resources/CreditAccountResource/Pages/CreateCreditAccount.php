<?php

declare(strict_types=1);

namespace App\Filament\Resources\CreditAccountResource\Pages;

use App\Filament\Resources\CreditAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCreditAccount extends CreateRecord
{
    protected static string $resource = CreditAccountResource::class;
}
