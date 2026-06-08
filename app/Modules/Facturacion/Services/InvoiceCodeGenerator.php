<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Services;

use App\Modules\Facturacion\Models\Invoice;
use Illuminate\Support\Facades\DB;

final class InvoiceCodeGenerator
{
    public function next(string $tenantId, string $prefix = 'FV'): array
    {
        return DB::transaction(function () use ($tenantId, $prefix) {
            $last = Invoice::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('prefix', $prefix)
                ->lockForUpdate()
                ->orderBy('sequence', 'desc')
                ->first();

            $sequence = $last ? $last->sequence + 1 : 1;

            return [
                'prefix' => $prefix,
                'sequence' => $sequence,
                'document_number' => $prefix.'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            ];
        });
    }
}
