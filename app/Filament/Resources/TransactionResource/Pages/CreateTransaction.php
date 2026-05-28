<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Services\Transactions\TransactionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $service = app(TransactionService::class);

        $itemsData = $data['items'] ?? [];
        unset($data['items']);

        $data['status'] = 'draft';

        return $service->createWithItems($data, $itemsData);
    }
}
