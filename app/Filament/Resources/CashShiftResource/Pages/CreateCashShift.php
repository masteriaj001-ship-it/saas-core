<?php

declare(strict_types=1);

namespace App\Filament\Resources\CashShiftResource\Pages;

use App\Filament\Resources\CashShiftResource;
use App\Modules\Caja\Exceptions\TurnoCerradoException;
use App\Modules\Caja\Services\CashMovementService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateCashShift extends CreateRecord
{
    protected static string $resource = CashShiftResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return CashMovementService::openShift(
                auth()->user(),
                (float) $data['initial_amount'],
                $data['notes'] ?? null,
            );
        } catch (TurnoCerradoException $exception) {
            throw ValidationException::withMessages([
                $this->form->getStatePath().'.initial_amount' => $exception->getMessage(),
            ]);
        }
    }
}
