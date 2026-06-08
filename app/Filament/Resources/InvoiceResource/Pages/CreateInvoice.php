<?php

declare(strict_types=1);

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Modules\Facturacion\Services\InvoiceCodeGenerator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $code = app(InvoiceCodeGenerator::class)->next(
            tenantId: auth()->user()->tenant_id,
            prefix: $data['prefix'] ?? 'FV',
        );

        $data['prefix'] = $code['prefix'];
        $data['sequence'] = $code['sequence'];
        $data['document_number'] = $code['document_number'];

        if ($data['status'] === 'issued' && empty($data['issued_at'])) {
            $data['issued_at'] = now();
        }

        return parent::handleRecordCreation($data);
    }
}
