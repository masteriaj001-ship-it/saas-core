<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Services;

use App\Models\Tenant;
use App\Modules\Facturacion\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

class DocumentSequenceService
{
    private const PREFIXES = [
        'invoice' => 'FE',
        'credit_note' => 'NC',
        'pos' => 'POS',
    ];

    public function nextSequence(Tenant $tenant, string $type): int
    {
        return DB::transaction(function () use ($tenant, $type): int {
            $seq = DocumentSequence::where('tenant_id', $tenant->id)
                ->where('type', $type)
                ->lockForUpdate()
                ->first();

            if ($seq === null) {
                $seq = DocumentSequence::create([
                    'tenant_id' => $tenant->id,
                    'type' => $type,
                    'prefix' => self::PREFIXES[$type] ?? strtoupper($type),
                    'last_sequence' => 0,
                ]);
            }

            $seq->increment('last_sequence');

            return $seq->last_sequence;
        });
    }

    public function formatNumber(Tenant $tenant, string $type, int $sequence): string
    {
        $prefix = self::PREFIXES[$type] ?? strtoupper($type);

        return sprintf('%s-%06d', $prefix, $sequence);
    }
}
